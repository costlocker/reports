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
                    'console' => new Profitability\ProfitabilityToConsole(),
                    'xls' => new Profitability\ProfitabilityToXls($this->spreadsheet),
                ],
                'filename' => function (\DateTime $monthStart, \DateTime $monthEnd) {
                    return "{$monthStart->format('Y-m')} - {$monthEnd->format('Y-m')}";
                },
            ],
        ];
        $this->mailer = $mailer;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setName('report')
            ->addArgument('type', InputArgument::REQUIRED, implode(', ', array_keys($this->providers)))
            ->addOption('monthStart', 'ms', InputOption::VALUE_REQUIRED, 'First month', 'previous month')
            ->addOption('monthEnd', 'me', InputOption::VALUE_REQUIRED, 'Last month', 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('currency', 'c', InputOption::VALUE_REQUIRED, 'Currency', 'CZK')
            ->addOption('personsSettings', 'hh', InputOption::VALUE_REQUIRED, 'Person salary hours and position')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'Report recipients')
            ->addOption('cache', null, InputOption::VALUE_NONE, 'If costlocker responses are cached (useful when month report is generate for multiple months)');
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
        $settings->personsSettings = $input->getOption('personsSettings');

        $output->writeln([
            "<comment>Report</comment>",
            "<info>Months count:</info> " . count($interval),
            "<info>Months interval:</info> <{$monthStart->format('Y-m')}, {$monthEnd->format('Y-m')}>",
            "<info>API Url:</info> {$apiHost}",
            "<info>API Key:</info> {$apiKey}",
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

        $exporterType = $settings->email ? 'xls' : 'console';
        foreach ($reports as $report) {
            $reporter['exporters'][$exporterType]($report, $settings);
        }
        if ($settings->email) {
            $this->sendMail($settings, $reporter['filename']($monthStart, $monthEnd));
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
        list($apiHost, $apiKey) = explode('|', $input->getOption('host'));
        $client = Client\HttpClient::build($apiHost, $apiKey);
        if ($input->getOption('cache')) {
            $client = new Client\CachedClient(
                $client,
                __DIR__ . '/../var/cache',
                $input->getOption('host'),
                function ($text) use ($output) {
                    if ($output->isVerbose()) {
                        $output->writeln($text);
                    }
                }
            );
        }
        return [$client, $apiHost, $apiKey];
    }

    private function sendMail(ReportSettings $settings, $filename)
    {
        $xlsFile = $this->spreadsheetToFile($filename);
        $wasSent = $this->mailer->__invoke($settings->email, $xlsFile, $filename);
        if ($wasSent) {
            unlink($xlsFile);
            $settings->output->writeln('<comment>E-mail was sent!</comment>');
        } else {
            $settings->output->writeln('<error>E-mail was not sent!</error>');
        }
    }

    private function spreadsheetToFile($filename)
    {
        $normalizedName = str_replace(' ', '', $filename);
        $xlsFile = "var/reports/{$normalizedName}.xlsx";
        $writer = IOFactory::createWriter($this->spreadsheet, 'Xlsx');
        $writer->save($xlsFile);
        return $xlsFile;
    }
}
