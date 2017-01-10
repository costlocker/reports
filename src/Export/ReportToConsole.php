<?php

namespace Costlocker\Reports\Export;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Costlocker\Reports\CostlockerReport;

class ReportToConsole
{
    public function __invoke(CostlockerReport $report, OutputInterface $output)
    {
        $headers = [
            'Person',
            'Project',
            'Client',
            'Hours',
            'Salary',
            'Tracked Hours',
            'Estimated Hours',
            'Billable',
            'Non-Billable',
            'Client Rate',
        ];

        $table = new Table($output);
        $table->setHeaders($headers);
        foreach ($report->getActivePeople() as $person) {
            $table->addRow([
                "<info>{$person['name']}</info>",
                '',
                '',
                $person['salary_hours'],
                $person['salary_amount'],
                '',
            ]);
            foreach ($person['projects'] as $project) {
                $table->addRow([
                    $person['name'],
                    $project['name'],
                    $project['client'],
                    '',
                    '',
                    $project['hrs_tracked_month'],
                    $project['hrs_budget'],
                    "{$project['hrs_budget']} - ({$project['hrs_tracked_total']} - "
                        . "{$project['hrs_tracked_after_month']} - {$project['hrs_tracked_month']})",
                    'tracked - billable',
                    $project['client_rate'],
                ]);
            }
        }
        $table->render();
    }
}
