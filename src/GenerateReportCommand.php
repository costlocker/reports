<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use GuzzleHttp\Client;

class GenerateReportCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('report')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Select month', 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'Report recipients');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $month = new \DateTime($input->getOption('date'));
        list($apiHost, $apiKey) = explode('|', $input->getOption('host'));
        $email = $input->getOption('email');

        $output->writeln([
            "<comment>Report</comment>",
            "<info>Month:</info> {$month->format('Y-m')}",
            "<info>API Url:</info> {$apiHost}",
            "<info>API Key:</info> {$apiKey}",
            "<info>E-mail Recipients:</info> {$email}",
        ]);

        $client = new CostlockerClient(new Client([
            'base_uri' => $apiHost,
            'http_errors' => false,
            'headers' => [
                'Api-Token' => $apiKey,
            ],
        ]));
        $projects = $client->projects();

        $table = new Table($output);
        $table->setHeaders(['Project', 'Client']);
        foreach ($projects as $project) {
            $table->addRow([
                $project['name'],
                $project['client'],
            ]);
        }
        $table->render();
    }
}
