<?php

namespace Costlocker\Reports\CLI;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Costlocker\Reports\GenerateReport;
use Costlocker\Reports\GenerateReportPresenter;
use Costlocker\Reports\ReportsRegistry;
use Costlocker\Reports\Config\Enum;

class GenerateReportCommand extends Command implements GenerateReportPresenter
{
    private $generateReport;
    private $registry;

    private $configFile;
    private $currentConfig;
    private $output;
    private $progressbar;

    public function __construct(GenerateReport $g, ReportsRegistry $r)
    {
        $this->generateReport = $g;
        $this->registry = $r;
        parent::__construct();
    }

    protected function configure()
    {
        $doc = [
            'filter' => 'Additional filter for reports (e.g. filter profitability by position)',
            'type' => 'Available reports: ' . implode(', ', $this->registry->getAvailableTypes()),
            'dateRange' => '"' . implode('", "', [
                Enum::DATE_RANGE_ALLTIME,
                Enum::DATE_RANGE_WEEK,
                Enum::DATE_RANGE_MONTHS,
                Enum::DATE_RANGE_YEAR,
            ]) . '"',
            'date' => 'Meaning depends on selected dateRange',
        ];
        $this
            ->setName('report')
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to json config file')
            // deprecated configuration
            ->addArgument('type', InputArgument::OPTIONAL, $doc['type'])
            ->addOption(
                'dateRange',
                'dr',
                InputOption::VALUE_REQUIRED,
                $doc['dateRange'],
                Enum::DATE_RANGE_MONTHS
            )
            ->addOption('dateStart', 'ds', InputOption::VALUE_REQUIRED, $doc['date'], 'previous month')
            ->addOption('dateEnd', 'de', InputOption::VALUE_REQUIRED, $doc['date'], 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('currency', 'c', InputOption::VALUE_REQUIRED, 'Currency', Enum::CURRENCY_CZK)
            ->addOption('personsSettings', 'hh', InputOption::VALUE_REQUIRED, 'Person salary hours and position')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'Report recipients')
            ->addOption('drive', 'd', InputOption::VALUE_OPTIONAL, 'Local directory with Google Drive configuration')
            ->addOption('drive-client', 'dc', InputOption::VALUE_OPTIONAL, 'Shared client (client.json, token.json)')
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, $doc['filter'])
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'xls,console,...', Enum::FORMAT_XLS);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        if (!$input->getOption('config')) {
            $cliConfig = $this->buildConfig($input);
            $newConfig = "config-{$cliConfig['reportType']}.json";
            file_put_contents($newConfig, json_encode($cliConfig, JSON_PRETTY_PRINT));
            return $this->error(
                "<error>You are using legacy CLI options, please use --config</error>",
                [
                    "New config file created: {$newConfig}",
                    "bin/console --config {$newConfig}",
                ]
            );
        }

        $this->configFile = $input->getOption('config');
        $this->currentConfig = json_decode(file_get_contents($this->configFile), true);
        $this->generateReport->__invoke($this->currentConfig, $this);
    }

    private function buildConfig(InputInterface $input)
    {
        list($type, $exportSettings) = explode(':', $input->getArgument('type')) + [1 => null];
        list($apiHost, $apiKeysSeparated) = explode('|', $input->getOption('host'), 2) + [
            1 => 'https://new.costlocker.com',
            2 => 'token from https://new.costlocker.com/api-token',
        ];
        $apiKeys = explode('|', $apiKeysSeparated);
        $defaultReport = $this->registry->getDefaultReport($type);
        return [
            'costlocker' => [
                'host' => $apiHost,
                'tokens' => $apiKeys,
            ],
            'reportType' => $type,
            'config' => [
                'title' => "{$type} report",
                'currency' => $input->getOption('currency'),
                'format' => $this->registry->getDefaultFormat($type),
                'dateRange' => $defaultReport['config']['dateRange'],
                'customDates' => array_values(array_filter([
                    $this->convertDate($input->getOption('dateStart')),
                    $this->convertDate($input->getOption('dateEnd')),
                ])),
            ],
            'customConfig' => array_merge(
                $defaultReport['customConfig'],
                [
                    ['key' => 'exportSettings', 'format' => 'text', 'value' => $exportSettings ?: ''],
                    ['key' => 'filter', 'format' => 'text', 'value' => $input->getOption('filter') ?: ''],
                ]
            ),
            'export' => [
                'filename' => "report-{$type}",
                'email' => $input->getOption('email'),
                'googleDrive' => [
                    'folderId' => null,
                    'files' => [],
                ],
            ],
        ];
    }

    private function convertDate($date)
    {
        if (!$date) {
            return null;
        }
        return (new \DateTime($date))->format('Y-m-d');
    }

    public function startExtracting(array $runtimeConfig)
    {
        $dates = $runtimeConfig['dates'];
        $interval = '-';
        if ($dates) {
            $interval = '<' . reset($dates)->format('Y-m') . ', ' . end($dates)->format('Y-m') . '>';
        }
        $this->output->writeln([
            "<comment>Report</comment>",
            "<info>Months count:</info> " . count($dates),
            "<info>Months interval:</info> {$interval}",
            "<info>Custom config:</info> " . json_encode($runtimeConfig['customConfig']),
            "<info>Uploaders:</info> " . implode(', ', array_keys(array_filter($runtimeConfig['export']))),
            '',
        ]);
        $this->progressbar = new ProgressBar($this->output, count($dates));
        if ($dates) {
            $this->progressbar->start();
        }
        $this->durations['apiStart'] = microtime(true);
    }

    public function finishExtracting(\DateTime $date)
    {
        $this->progressbar->advance();
        if (!array_key_exists('dates', $this->durations)) {
            $this->durations['dates'] = [];
        }
        $this->durations['apiDates'][$date->format('Y-m-d')] = microtime(true);
    }

    public function startTransforming()
    {
        $this->progressbar->finish();
        $this->output->writeln('');
        $this->durations['exportStart'] = microtime(true);
    }

    public function startLoading()
    {
        $this->durations['uploadStart'] = microtime(true);
    }

    public function finish(array $exports)
    {
        $googleDriveFiles = $exports['googleDrive'] ?? [];
        if (is_array($googleDriveFiles) && count($googleDriveFiles)) {
            $this->currentConfig['export']['googleDrive']['files'] = $googleDriveFiles;
            file_put_contents(
                $this->configFile,
                json_encode($this->currentConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
        $this->output->writeln([
            '',
            "<comment>XLS export</comment>",
            "<info>File:</info> {$exports['filesystem']}",
            $exports['email'] ? '<info>E-mail was sent!</info>' : '<error>E-mail was not sent!</error>',
            $exports['googleDrive'] ? '<info>Report uploaded to gdrive</info>' : '<error>No gdrive upload</error>',
        ]);
        $this->output->writeln([
            '',
            "<comment>Durations [s]</comment>",
        ]);
        foreach ($this->calculateDurations() as $type => $duration) {
            $this->output->writeln("<info>{$type}:</info> {$duration}");
        }
    }

    public function error($message, $detail)
    {
        $this->output->writeln([
            "<error>{$message}</error>",
            '<comment>' . json_encode($detail, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</comment>',
        ]);
    }

    private function calculateDurations()
    {
        $end = microtime(true);
        return [
            'total' => $end - $this->durations['exportStart'],
            'api' => $this->durations['exportStart'] - $this->durations['apiStart'],
            'export' => $this->durations['uploadStart'] - $this->durations['exportStart'],
            'upload' => $end - $this->durations['uploadStart'],
        ];
    }
}
