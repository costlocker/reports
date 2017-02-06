<?php

namespace Costlocker\Reports\Inspiro;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Costlocker\Reports\ReportSettings;

class InspiroToConsole
{
    public function __invoke(array $clients, ReportSettings $settings)
    {
        $headers = [
            [
                new TableCell('Client', array('rowspan' => 2)),
                new TableCell('Running projects', array('colspan' => 3)),
                new TableCell('Finished projects', array('colspan' => 3)),
            ],
            [
                'Revenue',
                'Project Expenses',
                'Projects Count',
                'Revenue',
                'Project Expenses',
                'Projects Count',
            ]
        ];

        $table = new Table($settings->output);
        $table->setHeaders($headers);
        foreach ($clients as $client => $billing) {
            $table->addRow([
                "<comment>{$client}</comment>",
                $billing['running']['revenue'],
                $billing['running']['expenses'],
                $billing['running']['projects'],
                $billing['finished']['revenue'],
                $billing['finished']['expenses'],
                $billing['finished']['projects'],
            ]);
        }
        $table->render();
    }
}
