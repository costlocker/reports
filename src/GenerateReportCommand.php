<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;

class GenerateReportCommand extends Command
{
    private $exporters;

    public function __construct(Mailer $mailer)
    {
        parent::__construct();
        $this->exporters = [
            'console' => new Export\ReportToConsole(),
            'xls' => new Export\ReportToXls($mailer),
        ];
    }

    protected function configure()
    {
        $this
            ->setName('report')
            ->addOption('monthStart', 'ms', InputOption::VALUE_REQUIRED, 'First month', 'previous month')
            ->addOption('monthEnd', 'me', InputOption::VALUE_REQUIRED, 'Last month', 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('hardcodedHours', 'hh', InputOption::VALUE_REQUIRED, 'Hardcoded salary hours')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'Report recipients');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $monthStart = new \DateTime($input->getOption('monthStart'));
        $monthEnd = new \DateTime($input->getOption('monthEnd'));
        $interval = Dates::getMonthsBetween($monthStart, $monthEnd);

        list($apiHost, $apiKey) = explode('|', $input->getOption('host'));
        $settings = new ReportSettings();
        $settings->email = $input->getOption('email');
        $settings->hardcodedHours = $input->getOption('hardcodedHours');

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
        $client = CostlockerClient::build($apiHost, $apiKey);
        $reports = [];
        foreach ($interval as $month) {
            $reports[] = $client($month);
            $progressbar->advance();
        }
        $progressbar->finish();
        $output->writeln('');
        $endApi = microtime(true);

        $exporterType = $settings->email ? 'xls' : 'console';
        foreach ($reports as $report) {
            $this->exporters[$exporterType]($report, $output, $settings);
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
}
