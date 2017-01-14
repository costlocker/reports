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
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        parent::__construct();
        $this->exporters = [
            'console' => new Export\ReportToConsole(),
            'xls' => new Export\ReportToXls(),
        ];
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this
            ->setName('report')
            ->addOption('monthStart', 'ms', InputOption::VALUE_REQUIRED, 'First month', 'previous month')
            ->addOption('monthEnd', 'me', InputOption::VALUE_REQUIRED, 'Last month', 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('currency', 'c', InputOption::VALUE_REQUIRED, 'Currency', 'CZK')
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
        $settings->output = $output;
        $settings->email = $input->getOption('email');
        $settings->currency = $input->getOption('currency');
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
            $this->exporters[$exporterType]($report, $settings);
        }
        if ($settings->email) {
            $this->sendMail($settings, "{$monthStart->format('Y-m')} - {$monthEnd->format('Y-m')}");
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

    private function sendMail(ReportSettings $settings, $selectedMonths)
    {
        $xlsFile = $this->exporters['xls']->toFile($selectedMonths);
        $wasSent = $this->mailer->__invoke($settings->email, $xlsFile, $selectedMonths);
        if ($wasSent) {
            unlink($xlsFile);
            $settings->output->writeln('<comment>E-mail was sent!</comment>');
        } else {
            $settings->output->writeln('<error>E-mail was not sent!</error>');
        }
    }
}
