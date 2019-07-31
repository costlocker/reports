<?php

namespace Costlocker\Reports\Custom\Projects;

use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\ReportSettings;

class ProjectsOverviewExtractor extends Extractor
{
    public static function getConfig(): ExtractorBuilder
    {
        return ExtractorBuilder::buildFromJsonFile(__DIR__ . '/ProjectsOverview.json')
            ->transformToXls(ProjectsOverviewToXls::class);
    }

    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    public function __invoke(ReportSettings $s): array
    {
        $clientIds = $s->customConfig['clientIds'];
        $data = $this->request([
            'Simple_Clients' => [
                'params' => [],
                'convert' => function (array $client) use ($s) {
                    return [
                        'id' => $client['id'],
                        'name' => $client['name'],
                        'is_active' => !$client['deactivated'],
                        'url' => $s->costlocker->url(['path' => "/clients/detail/{$client['id']}/overview"]),
                        'projects' => [],
                    ];
                },
            ],
            'Simple_Projects' => [
                'params' => [
                    'client' => $clientIds,
                ],
                'convert' => function (array $project) use ($s) {
                    return [
                        'id' => $project['id'],
                        'name' => $project['name'],
                        'state' => $project['state'],
                        'client_id' => $project['client_id'],
                        'project_id' => $project['jobid'],
                        'budget' => [
                            'type' => $project['budget']['type'],
                            'progress_type' => $project['budget']['progress_type'],
                            'bill_exceeded_estimates' => $project['budget']['bill_exceeded_estimates'],
                            'total' => $project['budget']['progress_type']
                                ? "{$project['budget']['type']} ({$project['budget']['progress_type']})"
                                : $project['budget']['type'],
                        ],
                        'dates' => [
                            'start' => \DateTime::createFromFormat('Y-m-d', $project['da_start']),
                            'end' => \DateTime::createFromFormat('Y-m-d', $project['da_end']),
                        ],
                        'financialMetrics' => [
                            'revenue' => $project['revenue'],
                            'discount' => $project['disc'],
                        ],
                        'url' => $s->costlocker->url(['path' => "/projects/detail/{$project['id']}/overview"]),
                    ];
                },
            ],
        ]);
        $projects = [];
        list($aggregatedExpenses, $allExpenses) = $this->loadProjectExpenses(array_keys($data['Simple_Projects']));
        list($aggregatedBilling, $allBilling) = $this->loadProjectBilling(array_keys($data['Simple_Projects']));
        $people = $this->loadPeople(array_keys($data['Simple_Projects']));
        foreach ($data['Simple_Projects'] as $project) {
            if ($clientIds && !in_array($project['client_id'], $clientIds)) {
                continue;
            }
            $projects[$project['id']] = [
                'client' => $data['Simple_Clients'][$project['client_id']]['name'],
                'financialMetrics' => [
                    'peopleDiscount' => $project['financialMetrics']['discount'],
                    'peopleRevenue' =>
                        $project['financialMetrics']['revenue'] +
                        $project['financialMetrics']['discount'] -
                        $aggregatedExpenses[$project['id']]['billed'],
                    'peopleCosts' => $people[$project['id']]['costs_people'],
                    'overheadCosts' => $people[$project['id']]['costs_overheads'],
                    'peopleRevenueGain' => $people[$project['id']]['gain'],
                    'peopleRevenueLoss' => $people[$project['id']]['loss'],
                    'expensesRevenue' => $aggregatedExpenses[$project['id']]['billed'],
                    'expensesCosts' => $aggregatedExpenses[$project['id']]['purchased'],
                    'billingBilled' => $aggregatedBilling[$project['id']]['billed'],
                ],
                'hours' => [
                    'estimated' => $people[$project['id']]['estimated'],
                    'tracked' => $people[$project['id']]['tracked'],
                    'billable' => $people[$project['id']]['billable'],
                ],
            ] + $project;
            $remainingBilling =
                $project['financialMetrics']['revenue'] -
                $aggregatedBilling[$project['id']]['billed'] -
                $aggregatedBilling[$project['id']]['planned'];
            if (abs($remainingBilling) > 0.1) {
                $allBilling[] = [
                    'project_id' => $project['id'],
                    'billed' => 0,
                    'planned' => 0,
                    'remaining' => $remainingBilling,
                    'name' => '',
                    'status' => 'remaining',
                    'dates' => [
                        'billed' => null,
                    ],
                ];
            }
        }
        return [
            'projects' => $projects,
            'expenses' => $allExpenses,
            'billing' => $allBilling,
        ];
    }

    private function loadPeople(array $projectIds)
    {
        $projectActivities = [];
        $people = $this->bulkProjects([
            'Simple_Projects_Ce' => [
                'params' => [
                    'project' => $projectIds,
                    'withLossGain' => true,
                ],
                'convert' => function (array $cost) use (&$projectActivities) {
                    if (!array_key_exists($cost['project_id'], $projectActivities)) {
                        $projectActivities[$cost['project_id']] = [];
                    }
                    $activityKey = "{$cost['project_id']}_{$cost['activity_id']}";
                    if (!array_key_exists($activityKey, $projectActivities)) {
                        $projectActivities[$cost['project_id']][$activityKey] = [
                            'loss' => $cost['budget_activity']['revenue_loss'],
                            'gain' => $cost['budget_activity']['revenue_gain'],
                        ];
                    }
                    return [
                        'project_id' => $cost['project_id'],
                        'costs_people' => $cost['hrs_tracked'] * $cost['person_rate'],
                        'costs_overheads' => $cost['hrs_tracked'] * $cost['person_overhead'],
                        'estimated' => $cost['hrs_budget'],
                        'tracked' => $cost['hrs_tracked'],
                        'billable' => $cost['hrs_billable'],
                        // revenue loss/gain aggregated at activity level because of time_estimates.activity
                        'loss' => 0,
                        'gain' => 0,
                    ];
                },
            ],
        ]);
        $fields = ['costs_people', 'costs_overheads', 'estimated', 'tracked', 'billable', 'loss', 'gain'];
        list($projects) = $this->aggregateByProjects($projectIds, $people, $fields);
        foreach ($projectActivities as $projectId => $activities) {
            foreach ($activities as $activity) {
                $projects[$projectId]['loss'] += $activity['loss'];
                $projects[$projectId]['gain'] += $activity['gain'];
            }
        }
        return $projects;
    }

    private function loadProjectExpenses(array $projectIds)
    {
        $allExpenses = $this->bulkProjects([
            'Simple_Projects_Expenses' => [
                'params' => [
                    'project' => $projectIds,
                ],
                'convert' => function (array $expense) {
                    return [
                        'project_id' => $expense['project_id'],
                        'purchased' => $expense['buy'],
                        'billed' => $expense['sell'],
                        'name' => $expense['name'],
                        'dates' => [
                            'purchased' => $expense['date']
                                ? \DateTime::createFromFormat('Y-m-d', $expense['date']) : null,
                        ],
                    ];
                },
            ],
        ]);
        return $this->aggregateByProjects($projectIds, $allExpenses, ['purchased', 'billed']);
    }

    private function loadProjectBilling(array $projectIds)
    {
        $allBilling = $this->bulkProjects([
            'Simple_Projects_Billing' => [
                'params' => [
                    'project' => $projectIds,
                ],
                'convert' => function (array $billing) {
                    return [
                        'project_id' => $billing['project_id'],
                        'billed' => $billing['issued'] ? $billing['amount'] : 0,
                        'planned' => !$billing['issued'] ? $billing['amount'] : 0,
                        'remaining' => 0,
                        'name' => $billing['name'],
                        'status' => $billing['issued'] ? 'billed' : 'planned',
                        'dates' => [
                            'billed' => \DateTime::createFromFormat('Y-m-d', $billing['date']),
                        ],
                    ];
                },
            ],
        ]);
        return $this->aggregateByProjects($projectIds, $allBilling, ['billed', 'planned']);
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

    private function aggregateByProjects(array $projectIds, array $projectEntities, array $fields)
    {
        $groupedByProjects = $this->client->map($projectEntities, 'project_id');
        $aggregatedByProjects = array_fill_keys($projectIds, array_fill_keys($fields, 0));
        $allEntities = [];
        foreach ($groupedByProjects as $projectId => $entity) {
            foreach ($entity as $e) {
                foreach ($fields as $field) {
                    $aggregatedByProjects[$projectId][$field] += $e[$field];
                }
                $allEntities[] = $e;
            }
        }
        return [$aggregatedByProjects, $allEntities];
    }

    private function request(array $endpoints)
    {
        $request = array_map(
            function (array $config) {
                return $config['params'];
            },
            $endpoints
        );
        $rawData = $this->client->request($request);

        $result = [];
        foreach ($rawData as $endpoint => $rows) {
            $result[$endpoint] = [];
            foreach ($rows as $index => $row) {
                $id = isset($row['id']) ? $row['id'] : $index;
                $result[$endpoint][$id] = $endpoints[$endpoint]['convert']($row);
            }
        }
        return $result;
    }
}
