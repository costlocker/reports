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
            'type' => '<comment>Available reports: </comment>' . implode(', ', $this->registry->getAvailableTypes()),
            'deprecated' => 'Deprecated option from v2',
            'schema' => realpath(__DIR__ . '/../Reports/Config/schema.json'),
        ];
        $this
            ->setName('report')
            ->setDescription(
                "Generate report <comment>bin/console report --config report.json</comment>, " .
                "config file is created if <comment>--config</comment> is missing"
            )
            ->setHelp(<<<TXT
Use JSON file
<comment>\$ bin/console report --config Projects.Overview.json</comment>

You can check JSON schema for supported fields and values - '{$doc['schema']}'.
Generate JSON template by using other CLI options.
<comment>\$ bin/console report Projects.Overview --host "https://new.costlocker.com|<YOUR_API_KEY>"</comment>
TXT
            )
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Path to json config file')
            // deprecated configuration
            ->addArgument('type', InputArgument::OPTIONAL, $doc['type'])
            ->addOption('monthStart', 'ms', InputOption::VALUE_REQUIRED, $doc['deprecated'])
            ->addOption('monthEnd', 'me', InputOption::VALUE_REQUIRED, $doc['deprecated'])
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, "{$doc['deprecated']} - apiUrl|apiKey")
            ->addOption('currency', 'c', InputOption::VALUE_REQUIRED, $doc['deprecated'])
            ->addOption('personsSettings', 'hh', InputOption::VALUE_REQUIRED, $doc['deprecated'])
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, "{$doc['deprecated']} - E-mail recipient")
            ->addOption('drive', 'd', InputOption::VALUE_OPTIONAL, $doc['deprecated'])
            ->addOption('drive-client', 'dc', InputOption::VALUE_OPTIONAL, $doc['deprecated'])
            ->addOption('cache', null, InputOption::VALUE_NONE, $doc['deprecated'])
            ->addOption('filter', 'f', InputOption::VALUE_OPTIONAL, $doc['deprecated'])
            ->addOption('format', null, InputOption::VALUE_REQUIRED, $doc['deprecated']);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        if (!$input->getOption('config')) {
            return $this->noConfigHelp($input);
        }

        $this->configFile = $input->getOption('config');
        $this->currentConfig = json_decode(file_get_contents($this->configFile), true);
        $this->generateReport->__invoke($this->currentConfig, $this);
    }

    private function noConfigHelp(InputInterface $input)
    {
        $cliConfig = $this->buildConfig($input);
        if (!$cliConfig) {
            return $this->error(
                "Report '{$input->getArgument('type')}' not found, please use one of available reporty types",
                [
                    'Available report types' => $this->registry->getAvailableTypes(),
                    'Help' => 'bin/console report --help'
                ]
            );
        }
        $newConfig = "config-{$cliConfig['reportType']}.json";
        file_put_contents($newConfig, json_encode($cliConfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $this->error(
            "<info>You are using deprecated CLI options, use --config for generating report</info>",
            [
                "New config file created: {$newConfig}",
                "bin/console --config {$newConfig}",
            ]
        );
    }

    private function buildConfig(InputInterface $input)
    {
        list($type, $exportSettings) = explode(':', $input->getArgument('type')) + [1 => null];
        if (!in_array($type, $this->registry->getAvailableTypes())) {
            return null;
        }
        list($apiHost, $apiKeysSeparated) = array_filter(explode('|', $input->getOption('host'), 2)) + [
            0 => 'https://new.costlocker.com',
            1 => 'token from https://new.costlocker.com/api-token',
        ];
        $apiKeys = explode('|', $apiKeysSeparated);
        $defaultReport = $this->registry->getDefaultReport($type);
        $oldOptionToCustomConfig = function ($key, $value) {
            return $value ? ['key' => $key, 'format' => 'text', 'value' => $value] : [];
        };
        return [
            'costlocker' => [
                'host' => $apiHost,
                'tokens' => $apiKeys,
            ],
            'reportType' => $type,
            'config' => array_filter([
                'title' => "{$type} report",
                'currency' => $input->getOption('currency'),
                'dateRange' => $defaultReport['config']['dateRange'],
                'customDates' => array_values(array_filter([
                    $this->convertDate($input->getOption('monthStart')),
                    $this->convertDate($input->getOption('monthEnd')),
                ])),
                'format' => $this->registry->getDefaultFormat($type),
            ]),
            'customConfig' => array_merge(
                $defaultReport['customConfig'],
                array_filter([
                    $oldOptionToCustomConfig('exportSettings', $exportSettings),
                    $oldOptionToCustomConfig('filter', $input->getOption('filter')),
                ])
            ),
            'export' => [
                'filename' => 'report-' . str_replace('.', '', $type),
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
        $interval = 'all-time';
        if ($dates) {
            $interval = '<' . reset($dates)->format('Y-m') . ', ' . end($dates)->format('Y-m') . '>';
        }
        $this->output->writeln([
            "<info>Report</info>: <comment>{$runtimeConfig['title']}</comment>",
            "<info>Date interval:</info> {$interval}",
            "<info>Custom config:</info> " . json_encode($runtimeConfig['customConfig']),
            "<info>Exporters:</info> " . implode(', ', array_keys(array_filter($runtimeConfig['export']))),
            '',
        ]);
        $this->progressbar = new ProgressBar($this->output, count($dates));
        if ($dates) {
            $this->progressbar->start();
        }
        $this->durations['extractStart'] = microtime(true);
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
        $this->durations['transformStart'] = microtime(true);
    }

    public function startLoading()
    {
        $this->durations['loadStart'] = microtime(true);
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
            "<comment>Export</comment>",
        ]);
        foreach ($exports as $type => $data) {
            $result = $data ? '<comment>OK</comment>' : '<error>Error</error>';
            if ($type == 'filesystem') {
                $result = $data;
            }
            $this->output->writeln("<info>{$type}:</info> {$result}");
        }
        $this->output->writeln([
            '',
            "<comment>Durations [s]</comment>",
        ]);
        foreach ($this->calculateDurations() as $type => $duration) {
            $this->output->writeln("<info>{$type}:</info> {$duration}");
        }
        $this->output->writeln('');
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
        $this->durations['end'] = microtime(true);
        $calcDuration = function ($start, $end) {
            return round($this->durations[$end] - $this->durations[$start], 3);
        };
        return [
            'total' => $calcDuration('extractStart', 'end'),
            'extract (api)' => $calcDuration('extractStart', 'transformStart'),
            'transform (xls|html)' => $calcDuration('transformStart', 'loadStart'),
            'load (export)' => $calcDuration('loadStart', 'end'),
        ];
    }
}
