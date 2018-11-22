<?php

namespace Costlocker\Reports\Profitability;

use Costlocker\Reports\CostlockerClient;
use Costlocker\Reports\Dates;

class ProfitabilityProvider
{
    private $client;

    public function __construct(CostlockerClient $client, $defaultSalary = 0)
    {
        $this->client = $client;
        $this->defaultSalary = $defaultSalary;
    }

    public function __invoke(\DateTime $month)
    {
        $report = new ProfitabilityReport();
        $report->selectedMonth = $month;
        $report->people = $this->people($month);
        $report->projects = $this->projects();
        return $report;
    }

    public function projects()
    {
        $rawData = $this->client->request([
            'Simple_Projects' => new \stdClass(),
            'Simple_Clients' => new \stdClass(),
            'Simple_Tags' => new \stdClass(),
        ]);

        $clients = $this->client->map($rawData['Simple_Clients'], 'id');
        $tags = $this->client->map($rawData['Simple_Tags'], 'id');
        $projects = [];

        foreach ($rawData['Simple_Projects'] as $project) {
            $projectTags = array_map(
                function (array $tag) use ($tags) {
                    return $tags[$tag['id']][0]['name'];
                },
                $project['tags']
            );
            sort($projectTags);
            $projects[$project['id']] = [
                'name' => $project['name'],
                'client' => $clients[$project['client_id']][0]['name'],
                'tags' => $projectTags,
            ];
        }

        return $projects;
    }

    public function people(\DateTime $month)
    {
        $rawData = $this->client->request([
            'Simple_People' => new \stdClass(),
        ]);

        $people = [];
        list($currentTimesheet, $activeProjects) = $this->groupTimesheetByPersonAndProject($month, $month, true);
        $personnelCosts = $this->personnelCosts($activeProjects);

        foreach ($rawData['Simple_People'] as $person) {
            $person += [
                'type' => 'salary',
                'salary_hours' => 8 * 20,
                'salary_amount' => $this->defaultSalary,
                'hourly_rate' => $this->defaultSalary / (8 * 20),
            ];

            $projects = array_map(
                function (array $projects) use ($currentTimesheet, $person) {
                    $project = reset($projects);
                    list($trackedHours, $billableHours, $revenue) =
                        $currentTimesheet[$person['id']][$project['project_id']] ?? [0, 0, 0];
                    return [
                        'client_rate' => $billableHours ? $revenue / $billableHours : 0,
                        'revenue' => $revenue,
                        'hrs_budget' => $this->client->sum($projects, 'hrs_budget'),
                        'hrs_tracked_month' => $trackedHours,
                        'hrs_billable_month' => $billableHours,
                    ];
                },
                $this->client->map($personnelCosts[$person['id']] ?? [], 'project_id')
            );
            $trackedHours = array_sum(
                array_map(
                    function (array $project) {
                        return $project['hrs_tracked_month'];
                    },
                    $projects
                )
            );

            $isEmployee = $person['type'] == 'salary';
            $people[$person['id']] = [
                'name' => "{$person['first_name']} {$person['last_name']}",
                'is_employee' => $isEmployee,
                'salary_hours' => $isEmployee ? $person['salary_hours'] : $trackedHours,
                'salary_amount' => $isEmployee ? $person['salary_amount'] : $trackedHours * $person['hourly_rate'],
                'hourly_rate' => $person['hourly_rate'],
                'projects' => $projects,
            ];
        }

        return $people;
    }

    public function personnelCosts(array $projects)
    {
        $rawData = $this->client->request([
            'Simple_Projects_Ce' => [
                'project' => $projects
            ],
        ]);

        return $this->client->map($rawData['Simple_Projects_Ce'], 'person_id');
    }

    private function groupTimesheetByPersonAndProject(\DateTime $monthStart, \DateTime $monthEnd)
    {
        $rawData = $this->client->request([
            'Simple_Timesheet' => [
                'datef' => $monthStart->format('Y-m-01'),
                'datet' => $monthEnd->format('Y-m-t'),
                'withBillable' => true,
                'withRates' => true,
            ],
        ]);
        $projects = [];
        $timesheet = array_map(
            function (array $personSheet) use (&$projects) {
                return array_map(
                    function ($projectSheet) use (&$projects) {
                        $projects = array_merge(
                            $projects,
                            array_keys($this->client->map($projectSheet, 'project'))
                        );
                        $trackedSeconds = $this->client->sum($projectSheet, 'interval');
                        $billableSeconds = 0;
                        $revenue = 0;
                        foreach ($projectSheet as $entry) {
                            $billableSeconds += $entry['billable'];
                            $revenue += ($entry['billable']  / 3600) * $entry['client_rate'];
                        }

                        return [$trackedSeconds / 3600, $billableSeconds / 3600, $revenue];
                    },
                    $this->client->map($personSheet, 'project')
                );
            },
            $this->client->map($rawData['Simple_Timesheet'], 'person')
        );

        return [$timesheet, array_unique($projects)];
    }
}
