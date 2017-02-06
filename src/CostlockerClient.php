<?php

namespace Costlocker\Reports;

use GuzzleHttp\Client;

class CostlockerClient
{
    private $client;
    private $defaultSalary;

    public static function build($apiHost, $apiKey)
    {
        return new CostlockerClient(new Client([
            'base_uri' => $apiHost,
            'http_errors' => true,
            'headers' => [
                'Api-Token' => $apiKey,
            ],
        ]));
    }

    public function __construct(Client $c, $defaultSalary = 0)
    {
        $this->client = $c;
        $this->defaultSalary = $defaultSalary;
    }

    public function __invoke(\DateTime $month)
    {
        $report = new CostlockerReport();
        $report->selectedMonth = $month;
        $report->people = $this->people($month);
        $report->projects = $this->projects();
        return $report;
    }

    public function inspiro()
    {
        $rawData = $this->request([
            'Simple_Projects' => new \stdClass(),
            'Simple_Clients' => new \stdClass(),
            'Simple_Projects_Expenses' => new \stdClass(),
        ]);

        $clients = $this->map($rawData['Simple_Clients'], 'id');
        $expenses = $this->map($rawData['Simple_Projects_Expenses'], 'project_id');
        $projects = [];

        foreach ($rawData['Simple_Projects'] as $project) {
            if ($project['state'] == 'running') {
                continue;
            }
            $projects[$project['id']] = [
                'name' => $project['name'],
                'client' => $clients[$project['client_id']][0]['name'],
                'revenue' => $project['revenue'],
                'billed' => $project['billed'],
                'expenses' => $this->sum(
                    $expenses[$project['id']] ?? [],
                    'sell'
                ),
            ];
        }

        return array_map(
            function (array $projects) {
                return [
                    'projects' => count($projects),
                    'revenue' => $this->sum($projects, 'revenue'),
                    'billed' => $this->sum($projects, 'billed'),
                    'expenses' => $this->sum($projects, 'expenses'),
                ];
            },
            $this->map($projects, 'client')
        );
    }

    public function projects()
    {
        $rawData = $this->request([
            'Simple_Projects' => new \stdClass(),
            'Simple_Clients' => new \stdClass(),
        ]);

        $clients = $this->map($rawData['Simple_Clients'], 'id');
        $projects = [];

        foreach ($rawData['Simple_Projects'] as $project) {
            $projects[$project['id']] = [
                'name' => $project['name'],
                'client' => $clients[$project['client_id']][0]['name'],
            ];
        }

        return $projects;
    }

    public function people(\DateTime $month)
    {
        $rawData = $this->request([
            'Simple_People' => new \stdClass(),
            'Simple_Projects_Ce' => new \stdClass(),
        ]);

        $people = [];
        $personnelCosts = $this->map($rawData['Simple_Projects_Ce'], 'person_id');
        $currentTimesheet = $this->groupTimesheetByPersonAndProject($month, $month);
        $nextTimesheet = $this->groupTimesheetByPersonAndProject(Dates::getNextMonth($month));

        foreach ($rawData['Simple_People'] as $person) {
            $person += [
                'type' => 'salary',
                'salary_hours' => 8 * 20,
                'salary_amount' => $this->defaultSalary,
                'hourly_rate' => $this->defaultSalary / (8 * 20),
            ];

            $projects = array_map(
                function (array $projects) use ($currentTimesheet, $nextTimesheet, $person) {
                    $project = reset($projects);

                    return [
                        'client_rate' => $project['client_rate'],
                        'hrs_budget' => $this->sum($projects, 'hrs_budget'),
                        'hrs_tracked_total' => $this->sum($projects, 'hrs_tracked'),
                        'hrs_tracked_month' => $currentTimesheet[$person['id']][$project['project_id']] ?? 0,
                        'hrs_tracked_after_month' => $nextTimesheet[$person['id']][$project['project_id']] ?? 0,
                    ];
                },
                $this->map($personnelCosts[$person['id']] ?? [], 'project_id')
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

    private function groupTimesheetByPersonAndProject(\DateTime $monthStart, \DateTime $monthEnd = null)
    {
        $rawData = $this->request([
            'Simple_Timesheet' => [
                'datef' => $monthStart->format('Y-m-01'),
                'datet' => $monthEnd ? $monthEnd->format('Y-m-t') : null,
            ],
        ]);

        return array_map(
            function (array $personSheet) {
                return array_map(
                    function ($projectSheet) {
                        $trackedSeconds = $this->sum($projectSheet, 'interval');

                        return $trackedSeconds / 3600;
                    },
                    $this->map($personSheet, 'project')
                );
            },
            $this->map($rawData['Simple_Timesheet'], 'person')
        );
    }

    private function request(array $request)
    {
        $response = $this->client->get(
            '/api-public/v1/',
            [
                'json' => $request,
            ]
        );
        return json_decode($response->getBody(), true);
    }

    private function map(array $rawData, $id)
    {
        $indexedItems = [];

        foreach ($rawData as $item) {
            $indexedItems[$item[$id]][] = $item;
        }

        return $indexedItems;
    }

    private function sum(array $rawData, $attribute)
    {
        return array_sum(
            array_map(
                function (array $project) use ($attribute) {
                    return $project[$attribute];
                },
                $rawData
            )
        );
    }
}
