<?php

namespace Costlocker\Reports\Inspiro;

use Costlocker\Reports\CostlockerClient;

class InspiroProvider
{
    private $client;

    public function __construct(CostlockerClient $client)
    {
        $this->client = $client;
    }

    public function __invoke()
    {
        $rawData = $this->client->request([
            'Simple_Projects' => new \stdClass(),
            'Simple_Clients' => new \stdClass(),
            'Simple_Projects_Expenses' => new \stdClass(),
        ]);

        $clients = $this->client->map($rawData['Simple_Clients'], 'id');
        $expenses = $this->client->map($rawData['Simple_Projects_Expenses'], 'project_id');
        $projects = [];

        foreach ($rawData['Simple_Projects'] as $project) {
            $projects[$project['id']] = [
                'name' => $project['name'],
                'client' => $clients[$project['client_id']][0]['name'],
                'state' => $project['state'],
                'revenue' => $project['revenue'],
                'expenses' => $this->client->sum(
                    $expenses[$project['id']] ?? [],
                    'sell'
                ),
            ];
        }

        $analyzeProjects = function (array $projects) {
            return [
                'projects' => count($projects),
                'revenue' => $this->client->sum($projects, 'revenue'),
                'expenses' => $this->client->sum($projects, 'expenses'),
            ];
        };

        return array_map(
            function (array $projects) use ($analyzeProjects) {
                $states = $this->client->map($projects, 'state');
                return [
                    'running' => $analyzeProjects($states['running'] ?? []),
                    'finished' => $analyzeProjects($states['finished'] ?? []),
                ];
            },
            $this->client->map($projects, 'client')
        );
    }
}
