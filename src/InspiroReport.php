<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

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
        $settings = new ReportSettings();
        $settings->output = $output;
        $client = CostlockerClient::build($apiHost, $apiKey);

        $provider = new \Costlocker\Reports\Inspiro\InspiroProvider($client);
        $export = new \Costlocker\Reports\Inspiro\InspiroToConsole();
        $export($provider(), $settings);
    }
}
