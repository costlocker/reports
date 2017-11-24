<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Costlocker\Reports\Export\Mailer;
use Costlocker\Reports\Export\GoogleDrive;

class GenerateReportCommand extends Command
{
    private $providers;
    private $mailer;
    private $spreadsheet;

    public function __construct(Mailer $mailer)
    {
        $this->spreadsheet = new Spreadsheet();
        $this->spreadsheet->removeSheetByIndex(0);
        $this->providers = [
            'profitability' => [
                'interval' => function (\DateTime $monthStart, \DateTime $monthEnd) {
                    return Dates::getMonthsBetween($monthStart, $monthEnd);
                },
                'provider' => Profitability\ProfitabilityProvider::class,
                'exporters' => [
                    'xls' => new Profitability\ProfitabilityToXls($this->spreadsheet),
                ],
                'filename' => function (\DateTime $monthStart, \DateTime $monthEnd, ReportSettings $settings) {
                    $company = str_replace(' ', '-', strtolower($settings->company));
                    return "{$company}-{$monthStart->format('Y-m')}";
                },
            ],
        ];
        $this->mailer = $mailer;
        parent::__construct();
    }

    protected function configure()
    {
        $doc = [
            'cache' => 'If costlocker responses are cached (useful when month report is generate for multiple months)',
            'filter' => 'Additional filter for reports (e.g. filter profitability by position)',
        ];
        $this
            ->setName('report')
            ->addArgument('type', InputArgument::REQUIRED, implode(', ', array_keys($this->providers)))
            ->addOption('monthStart', 'ms', InputOption::VALUE_REQUIRED, 'First month', 'previous month')
            ->addOption('monthEnd', 'me', InputOption::VALUE_REQUIRED, 'Last month', 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('currency', 'c', InputOption::VALUE_REQUIRED, 'Currency', 'CZK')
            ->addOption('personsSettings', 'hh', InputOption::VALUE_REQUIRED, 'Person salary hours and position')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'Report recipients')
            ->addOption('drive', 'd', InputOption::VALUE_OPTIONAL, 'Local directory with Google Drive configuration')
            ->addOption('drive-client', 'dc', InputOption::VALUE_OPTIONAL, 'Shared client (client.json, token.json)')
            ->addOption('cache', null, InputOption::VALUE_NONE, $doc['cache'])
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, $doc['filter'])
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'xls,console,...', 'xls');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $monthStart = new \DateTime($input->getOption('monthStart'));
        $monthEnd = new \DateTime($input->getOption('monthEnd'));
        list($type, $exportSettings) = explode(':', $input->getArgument('type')) + [1 => null];
        $reporter = $this->providers[$type];
        $interval = $reporter['interval']($monthStart, $monthEnd);

        list($client, $apiHost, $apiKey) = $this->buildClient($input, $output);
        $settings = new ReportSettings();
        $settings->output = $output;
        $settings->exportSettings = $exportSettings;
        $settings->email = $input->getOption('email');
        $settings->currency = $input->getOption('currency');
        $settings->filter = $input->getOption('filter');
        $settings->yearStart = $monthStart->format('Y');
        $settings->company = $client->getFirstCompanyName();
        $settings->generateProjectUrl = function ($input) use ($apiHost, $client) {
            $config = is_array($input) ? $input : [
                'path' => "/projects/detail/{$input}/overview",
                'project_id' => $input,
                'query' => [],
            ];
            if (!$config['project_id'] && !$config['query']) {
                return null;
            }
            $companyId = $client->getCompany($config['project_id'])['id'];
            $apiHost .= $companyId ? "/p/{$companyId}" : '';
            return "{$apiHost}{$config['path']}?" . http_build_query($config['query']);
        };
        $settings->getCompanyForProject = function ($projectId) use ($client) {
            return $client->getCompany($projectId)['name'];
        };

        $rawFilename = $reporter['filename']($monthStart, $monthEnd, $settings);
        $extraFilename = $settings->filter ? "-{$settings->filter}" : '';
        $filename = $rawFilename . $extraFilename;
        $settings->googleDrive = new GoogleDrive(
            $input->getOption('drive'),
            $input->getOption('drive-client'),
            $type,
            $filename
        );
        $csvFile = $input->getOption('personsSettings');
        $settings->personsSettings = $settings->googleDrive->downloadCsvFile($csvFile) ?: $csvFile;

        $output->writeln([
            "<comment>Report</comment>",
            "<info>Months count:</info> " . count($interval),
            "<info>Months interval:</info> <{$monthStart->format('Y-m')}, {$monthEnd->format('Y-m')}>",
            "<info>API Url:</info> {$apiHost}",
            "<info>API Key(s):</info> {$apiKey}",
            "<info>Filter:</info> {$settings->filter}",
            "<info>E-mail Recipients:</info> {$settings->email}",
            '',
        ]);

        $start = microtime(true);
        $progressbar = new ProgressBar($output, count($interval));
        $progressbar->start();
        $provider = new $reporter['provider']($client);
        $reports = [];
        foreach ($interval as $month) {
            $reports[] = $provider($month);
            $progressbar->advance();
        }
        $progressbar->finish();
        $output->writeln('');
        $endApi = microtime(true);

        $format = $input->getOption('format');
        if (!array_key_exists($format, $reporter['exporters'])) {
            $output->writeln([
                "<error>--format {$format} is not supported</error>",
                "<comment>Supported formats</comment>:" . implode(', ', array_keys($reporter['exporters'])),
            ]);
            return 1;
        }
        foreach ($reports as $report) {
            $reporter['exporters'][$format]($report, $settings);
        }
        if (method_exists($reporter['exporters'][$format], 'after')) {
            $reporter['exporters'][$format]->after();
        }
         
        if ($format == 'xls') {
            $this->exportToXls($settings, $filename);
        }
        $endExport = microtime(true);

        $output->writeln([
            '',
            "<comment>Durations [s]</comment>",
            "<info>API:</info> " . ($endApi - $start),
            "<info>Export:</info> " . ($endExport - $endApi),
            "<info>Total:</info> " . ($endExport - $start),
        ]);
    }

    private function buildClient(InputInterface $input, OutputInterface $output)
    {
        list($apiHost, $apiKeysSeparated) = explode('|', $input->getOption('host'), 2);
        $apiKeys = explode('|', $apiKeysSeparated);
        $clients = [];
        foreach ($apiKeys as $apiKey) {
            $tenantClient = Client\HttpClient::build($apiHost, $apiKey);
            if ($input->getOption('cache')) {
                $tenantClient = new Client\CachedClient(
                    $tenantClient,
                    __DIR__ . '/../var/cache',
                    $apiHost . '|' . sha1($apiKey),
                    function ($text) use ($output) {
                        if ($output->isVerbose()) {
                            $output->writeln($text);
                        }
                    }
                );
            }
            $clients[] = $tenantClient;
        }
        return [new Client\CompositeClient($clients), $apiHost, $apiKeysSeparated];
    }

    private function exportToXls(ReportSettings $settings, $filename)
    {
        $xlsFile = $this->spreadsheetToFile($filename);
        $wasEmailSent = $this->mailer->__invoke($settings->email, $xlsFile, $filename);
        $wasUploadedToDrive = $settings->googleDrive->upload($xlsFile, $settings);
        $settings->output->writeln([
            '',
            "<comment>XLS export</comment>",
            $wasEmailSent ? '<info>E-mail was sent!</info>' : '<error>E-mail was not sent!</error>',
            $wasUploadedToDrive ? '<info>Report uploaded to gdrive</info>' : '<error>No gdrive upload</error>',
        ]);
    }

    private function spreadsheetToFile($filename)
    {
        $normalizedName = str_replace([' ', '(', ')'], '-', $filename);
        $prettyName = str_replace('--', '-', trim($normalizedName, '-'));

        $xlsFile = "var/reports/{$prettyName}.xlsx";
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save($xlsFile);
        return $xlsFile;
    }
}
