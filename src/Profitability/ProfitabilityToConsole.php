<?php

namespace Costlocker\Reports\Profitability;

use Symfony\Component\Console\Helper\Table;
use Costlocker\Reports\ReportSettings;

class ProfitabilityToConsole
{
    public function __invoke(ProfitabilityReport $report, ReportSettings $settings)
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

        $table = new Table($settings->output);
        $table->setHeaders($headers);
        foreach ($report->getActivePeople() as $person) {
            $table->addRow([
                "<comment>{$person['name']} ({$settings->getPosition($person['name'])})</comment>",
                '',
                '',
                "{$person['salary_hours']} ({$settings->getHoursSalary($person['name'], 'tracked')})",
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
