<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class InspiroReport extends Command
{
    protected function configure()
    {
        $this
            ->setName('inspiro')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        list($apiHost, $apiKey) = explode('|', $input->getOption('host'));
        $client = CostlockerClient::build($apiHost, $apiKey);
        $projects = $client->inspiro();

        $headers = [
            'Client',
            'Revenue',
            'Project Expenses',
            'Revenue - Project Expenses',
            'Projects Count',
        ];

        $table = new Table($output);
        $table->setHeaders($headers);
        foreach ($projects as $client => $billing) {
            $table->addRow([
                "<comment>{$client}</comment>",
                $billing['revenue'],
                $billing['expenses'],
                $billing['revenue'] - $billing['expenses'],
                $billing['projects'],
            ]);
        }
        $table->render();
    }
}
