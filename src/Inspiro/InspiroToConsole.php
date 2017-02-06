<?php

namespace Costlocker\Reports\Inspiro;

use Symfony\Component\Console\Helper\Table;
use Costlocker\Reports\ReportSettings;

class InspiroToConsole
{
    public function __invoke(array $clients, ReportSettings $settings)
    {
        $headers = [
            'Client',
            'Revenue',
            'Project Expenses',
            'Revenue - Project Expenses',
            'Projects Count',
        ];

        $table = new Table($settings->output);
        $table->setHeaders($headers);
        foreach ($clients as $client => $billing) {
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
