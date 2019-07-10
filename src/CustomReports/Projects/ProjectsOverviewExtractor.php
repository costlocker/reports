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
                        'budget_type' => $project['budget']['progress_type']
                            ? "{$project['budget']['type']} ({$project['budget']['progress_type']})"
                            : $project['budget']['type'],
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
        $expenses = $this->loadProjectExpenses(array_keys($data['Simple_Projects']));
        $billing = $this->loadProjectBilling(array_keys($data['Simple_Projects']));
        $people = $this->loadPeople(array_keys($data['Simple_Projects']));
        foreach ($data['Simple_Projects'] as $project) {
            if ($clientIds && !in_array($project['client_id'], $clientIds)) {
                continue;
            }
            $projects[] = [
                'client' => $data['Simple_Clients'][$project['client_id']]['name'],
                'financialMetrics' => [
                    'peopleDiscount' => $project['financialMetrics']['discount'],
                    'peopleRevenue' =>
                        $project['financialMetrics']['revenue'] +
                        $project['financialMetrics']['discount'] -
                        $expenses[$project['id']]['billed'],
                    'peopleCosts' => $people[$project['id']]['costs'],
                    'expensesRevenue' => $expenses[$project['id']]['billed'],
                    'expensesCosts' => $expenses[$project['id']]['purchased'],
                    'billingBilled' => $billing[$project['id']]['billed'],
                ],
                'hours' => [
                    'estimated' => $people[$project['id']]['estimated'],
                    'tracked' => $people[$project['id']]['tracked'],
                    'billable' => $people[$project['id']]['billable'],
                ],
            ] + $project;
        }
        return $projects;
    }

    private function loadPeople(array $projectIds)
    {
        $people = $this->bulkProjects([
            'Simple_Projects_Ce' => [
                'params' => [
                    'project' => $projectIds,
                ],
                'convert' => function (array $cost) {
                    return [
                        'project_id' => $cost['project_id'],
                        'costs' => $cost['hrs_tracked'] * ($cost['person_rate'] + $cost['person_overhead']),
                        'estimated' => $cost['hrs_budget'],
                        'tracked' => $cost['hrs_tracked'],
                        'billable' => $cost['hrs_billable'],
                    ];
                },
            ],
        ]);
        return $this->aggregateByProjects($projectIds, $people, ['costs', 'estimated', 'tracked', 'billable']);
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
                    ];
                },
            ],
        ]);
        return $this->aggregateByProjects($projectIds, $allBilling, ['billed']);
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
                    'params' => ['project' => $projectIds],
                    'convert' => $config['convert'],
                ],
            ]);
            $result += $response[$endpoint];
        }
        return $result;
    }

    private function aggregateByProjects(array $projectIds, array $projectEntities, array $fields)
    {
        $groupedByProjects = $this->client->map($projectEntities, 'project_id');
        $aggregatedByProjects = array_fill_keys($projectIds, array_fill_keys($fields, 0));
        foreach ($groupedByProjects as $projectId => $entity) {
            foreach ($entity as $e) {
                foreach ($fields as $field) {
                    $aggregatedByProjects[$projectId][$field] += $e[$field];
                }
            }
        }
        return $aggregatedByProjects;
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
