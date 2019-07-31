<?php

namespace Costlocker\Reports\Custom\Company;

use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\ReportSettings;

class CompanyOverviewExtractor extends Extractor
{
    public static function getConfig(): ExtractorBuilder
    {
        return ExtractorBuilder::buildFromJsonFile(__DIR__ . '/CompanyOverview.json')
            ->transformToXls(CompanyOverviewToXls::class);
    }

    public function __invoke(ReportSettings $s): array
    {
        $buildUrl = function ($path) use ($s) {
            return $s->costlocker->url(['path' => $path]);
        };
        $isEndpointIgnored = function ($endpoint) use ($s) {
            $endpoints = $s->customConfig['extraTakeout'] ?? [];
            return !in_array($endpoint, $endpoints);
        };
        list($dateIntervals, $minDate, $maxDate) = $this->buildDateIntervals($s);
        $data = $this->loadMainEntities($buildUrl);
        return [
            'dateIntervals' => $dateIntervals,
            'people' => $data['Simple_People'],
            'clients' => $data['Simple_Clients'],
            'projects' => $data['Simple_Projects'],
            'expenses' => $this->loadProjectExpenses(array_keys($data['Simple_Projects']), $buildUrl),
            'billing' => $this->loadProjectBilling(array_keys($data['Simple_Projects']), $buildUrl),
            'projectPeople' => $this->loadProjectPeople(array_keys($data['Simple_Projects']), $isEndpointIgnored),
            'timesheet' => $this->loadTimesheet($minDate, $maxDate),
        ];
    }

    private function buildDateIntervals(ReportSettings $s)
    {
        $dateIntervals = array_map(
            function (array $interval) {
                $min = new \DateTime($interval['min']);
                $max = new \DateTime($interval['max']);
                return [
                    'title' => $interval['title'],
                    'min' => \DateTime::createFromFormat('Y-m-d H:i:s', "{$min->format('Y-m-01')} 00:00:00"),
                    'max' => \DateTime::createFromFormat('Y-m-d H:i:s', "{$max->format('Y-m-t')} 23:59:59"),
                ];
            },
            ($s->customConfig['dateIntervals'] ?? null) ?: [[
                'title' => date('Y'),
                'min' => 'last year 1st January',
                'max' => 'last year 31st December',
            ]]
        );
        usort($dateIntervals, function (array $a, array $b) {
            return $a['min']->getTimestamp() - $b['min']->getTimestamp();
        });
        $first = reset($dateIntervals);
        $last = end($dateIntervals);
        return [$dateIntervals, $first['min'], $last['max']];
    }

    private function loadMainEntities($buildUrl)
    {
        $tags = $this->request([
            'Simple_Tags' => [
                'params' => new \stdClass(),
                'convert' => function (array $tag) {
                    return [
                        'id' => $tag['id'],
                        'name' => $tag['name'],
                    ];
                },
            ],
        ]);
        return $this->request([
            'Simple_People' => [
                'params' => [
                    'withHistory' => true,
                ],
                'convert' => function (array $person) use ($buildUrl) {
                    return [
                        'id' => $person['id'],
                        'name' => "{$person['first_name']} {$person['last_name']}",
                        'is_active' => !$person['deactivated'],
                        'da_created' => $person['da_created'],
                        'current_salary' => [
                            'type' => $person['type'],
                            'hourly_rate' => $person['type'] == CostlockerEnum::HOURLY_RATE_TYPE
                                ? $person['hourly_rate']
                                : (
                                    $person['salary_hours'] != 0
                                        ? $person['salary_amount'] / $person['salary_hours'] : 0
                                ),
                        ],
                        'salary' => $person['salary'],
                        'url' => $buildUrl("/people/detail/{$person['id']}/overview"),
                    ];
                },
            ],
            'Simple_Clients' => [
                'params' => [
                    'withHistory' => true,
                ],
                'convert' => function (array $client) use ($buildUrl) {
                    return [
                        'id' => $client['id'],
                        'name' => $client['name'],
                        'is_active' => !$client['deactivated'],
                        'da_created' => $client['da_created'],
                        'url' => $buildUrl("/clients/detail/{$client['id']}/overview"),
                    ];
                },
            ],
            'Simple_Projects' => [
                'params' => new \stdClass(),
                'convert' => function (array $project) use ($tags, $buildUrl) {
                    $tagsNames = array_map(
                        function (array $tag) use ($tags) {
                            return $tags['Simple_Tags'][$tag['id']]['name'];
                        },
                        $project['tags']
                    );
                    sort($tagsNames);
                    return [
                        'id' => $project['id'],
                        'client_id' => $project['client_id'],
                        'name' => $project['name'],
                        'state' => $project['state'],
                        'dates' => [
                            'start' => $project['da_start'],
                            'end' => $project['da_end'],
                        ],
                        'budget_type' => $project['budget']['type'],
                        'tags' => $tagsNames,
                        'url' => $buildUrl("/projects/detail/{$project['id']}/overview"),
                    ];
                },
            ],
        ]);
    }

    private function loadProjectPeople(array $projectIds, $isEndpointIgnored)
    {
        if ($isEndpointIgnored('Simple_Projects_Ce')) {
            return [];
        }
        $activities = $this->request([
            'Simple_Activities' => [
                'params' => new \stdClass(),
                'convert' => function (array $tag) {
                    return [
                        'id' => $tag['id'],
                        'name' => $tag['name'],
                    ];
                },
            ],
        ]);
        return $this->bulkProjects([
            'Simple_Projects_Ce' => [
                'params' => [
                    'project' => $projectIds,
                ],
                'convert' => function (array $ce) use ($activities) {
                    $tasks = array_map(
                        function (array $task) {
                            return $task['name'];
                        },
                        $ce['tasks'] ?? []
                    );
                    return [
                        'project_id' => $ce['project_id'],
                        'person_id' => $ce['person_id'],
                        'activity' => $activities['Simple_Activities'][$ce['activity_id']]['name'],
                        'tasks' => $tasks,
                        'rates' => [
                            'client' => $ce['client_rate'],
                            'person' => $ce['person_rate'],
                            'overhead' => $ce['person_overhead'],
                        ],
                        'hours' => [
                            'estimated' => $ce['hrs_budget'],
                            'tracked' => $ce['hrs_tracked'],
                            'billable' => $ce['hrs_billable'],
                        ],
                    ];
                },
            ],
        ]);
    }

    private function loadProjectExpenses(array $projectIds, $buildUrl)
    {
        return $this->bulkProjects([
            'Simple_Projects_Expenses' => [
                'params' => [
                    'project' => $projectIds,
                ],
                'convert' => function (array $expense) use ($buildUrl) {
                    return [
                        'project_id' => $expense['project_id'],
                        'name' => $expense['name'],
                        'dates' => [
                            'purchased' => $expense['date'],
                        ],
                        'amounts' => [
                            'purchased' => $expense['buy'],
                            'billed' => $expense['sell'],
                        ],
                        'url' => $buildUrl("/projects/edit/{$expense['project_id']}/project-expenses"),
                    ];
                },
            ],
        ]);
    }

    private function loadProjectBilling(array $projectIds, $buildUrl)
    {
        return $this->bulkProjects([
            'Simple_Projects_Billing' => [
                'params' => [
                    'project' => $projectIds,
                ],
                'convert' => function (array $billing) use ($buildUrl) {
                    return [
                        'project_id' => $billing['project_id'],
                        'name' => $billing['name'],
                        'dates' => [
                            'billed' => $billing['date'],
                        ],
                        'amounts' => [
                            'billed' => $billing['amount'],
                            'status' => $billing['issued']
                                ? CostlockerEnum::BILLING_SENT : CostlockerEnum::BILLING_DRAFT,
                        ],
                        'url' => $buildUrl("/projects/detail/{$billing['project_id']}/billing"),
                    ];
                },
            ],
        ]);
    }

    private function loadTimesheet(\DateTime $dateStart, \DateTime $dateEnd)
    {
        $data = $this->request([
            'Simple_Timesheet_Month' => [
                'params' => [
                    'datef' => $dateStart->format('Y-m-01'),
                    'datet' => $dateEnd->format('Y-m-t'),
                    'nonproject' => true,
                ],
                'convert' => function (array $month) {
                    if (!$month['interval']) {
                        return null;
                    }
                    return [
                        'dates' => [
                            'month' => \DateTime::createFromFormat('Y-m-d H:i:s', "{$month['da_month']} 00:00:00"),
                        ],
                        'project_id' => $month['project'],
                        'person_id' => $month['person'],
                        'client_id' => $month['client'],
                        'seconds' => [
                            'tracked' => $month['interval'],
                            'billable' => $month['billable'],
                        ],
                    ];
                },
            ],
        ]);
        return $data['Simple_Timesheet_Month'];
    }

    private function bulkProjects(array $endpoints)
    {
        $bulkProjects = 500;
        $config = reset($endpoints);
        $endpoint = key($endpoints);
        $result = [];
        foreach (array_chunk($config['params']['project'], $bulkProjects) as $projectIds) {
            $response = $this->request([
                $endpoint => [
                    'params' => ['project' => $projectIds] + $config['params'],
                    'convert' => $config['convert'],
                ],
            ]);
            $result = array_merge($result, $response[$endpoint]);
        }
        return $result;
    }

    private function request(array $endpoints)
    {
        $request = array_map(
            function (array $config) {
                return $config['params'] ?? new \stdClass();
            },
            $endpoints
        );
        $rawData = $this->client->request($request);

        $result = [];
        foreach ($rawData as $endpoint => $rows) {
            $result[$endpoint] = [];
            foreach ($rows as $index => $row) {
                $id = isset($row['id']) ? $row['id'] : $index;
                $converted = $endpoints[$endpoint]['convert']($row);
                if ($converted === null) {
                    continue;
                }
                $result[$endpoint][$id] = $converted;
            }
        }
        return $result;
    }
}
