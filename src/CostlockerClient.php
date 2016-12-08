<?php

namespace Costlocker\Reports;

use GuzzleHttp\Client;

class CostlockerClient
{
    private $client;

    public function __construct(Client $c)
    {
        $this->client = $c;
    }

    public function __invoke(\DateTime $month)
    {
        $report = new CostlockerReport();
        $report->people = $this->people($month);
        $report->projects = $this->projects();
        return $report;
    }

    public function projects()
    {
        $response = $this->client->get(
            '/api-public/v1/',
            [
                'json' => [
                    'Simple_Projects' => new \stdClass(),
                    'Simple_Clients' => new \stdClass(),
                ],
            ]
        );
        $rawData = json_decode($response->getBody(), true);

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

    public function people()
    {
        $response = $this->client->get(
            '/api-public/v1/',
            [
                'json' => [
                    'Simple_People' => new \stdClass(),
                    'Simple_Projects_Ce' => new \stdClass(),
                ],
            ]
        );
        $rawData = json_decode($response->getBody(), true);

        $people = [];
        $personnelCosts = $this->map($rawData['Simple_Projects_Ce'], 'person_id');
        $timesheet = $this->groupTimesheetByPersonAndProject($rawData);

        foreach ($rawData['Simple_People'] as $person) {
            $person += [
                'salary_hours' => 8 * 20,
                'salary_amount' => 20000,
            ];
            $people[$person['id']] = [
                'name' => "{$person['first_name']} {$person['last_name']}",
                'salary_hours' => $person['salary_hours'],
                'salary_amount' => $person['salary_amount'],
                'projects' => array_map(
                    function (array $projects) use ($timesheet, $person) {
                        $project = reset($projects);

                        return [
                            'client_rate' => $project['client_rate'],
                            'hrs_budget' => $this->sum($projects, 'hrs_budget'),
                            'hrs_tracked_total' => $this->sum($projects, 'hrs_tracked'),
                            'hrs_tracked_month' => $timesheet[$person['id']][$project['project_id']] ?? 0,
                        ];
                    },
                    $this->map($personnelCosts[$person['id']], 'project_id')
                ),
            ];
        }

        return $people;
    }

    private function groupTimesheetByPersonAndProject(array $rawData)
    {
        if (!array_key_exists('Simple_Timesheet', $rawData)) {
            return [];
        }

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
