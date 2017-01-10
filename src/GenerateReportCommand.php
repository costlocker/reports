<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Select month', 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('hardcodedHours', 'hh', InputOption::VALUE_REQUIRED, 'Hardcoded salary hours')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'Report recipients');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $month = new \DateTime($input->getOption('date'));
        list($apiHost, $apiKey) = explode('|', $input->getOption('host'));
        $settings = new ReportSettings();
        $settings->email = $input->getOption('email');
        $settings->hardcodedHours = $input->getOption('hardcodedHours');

        $output->writeln([
            "<comment>Report</comment>",
            "<info>Month:</info> {$month->format('Y-m')}",
            "<info>API Url:</info> {$apiHost}",
            "<info>API Key:</info> {$apiKey}",
            "<info>E-mail Recipients:</info> {$settings->email}",
            '',
        ]);

        $client = CostlockerClient::build($apiHost, $apiKey);
        $report = $client($month);

        $exporterType = $settings->email ? 'xls' : 'console';
        $this->exporters[$exporterType]($report, $output, $settings);
    }
}
