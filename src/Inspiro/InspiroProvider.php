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
            if ($project['state'] == 'running') {
                continue;
            }
            $projects[$project['id']] = [
                'name' => $project['name'],
                'client' => $clients[$project['client_id']][0]['name'],
                'revenue' => $project['revenue'],
                'billed' => $project['billed'],
                'expenses' => $this->client->sum(
                    $expenses[$project['id']] ?? [],
                    'sell'
                ),
            ];
        }

        return array_map(
            function (array $projects) {
                return [
                    'projects' => count($projects),
                    'revenue' => $this->client->sum($projects, 'revenue'),
                    'billed' => $this->client->sum($projects, 'billed'),
                    'expenses' => $this->client->sum($projects, 'expenses'),
                ];
            },
            $this->client->map($projects, 'client')
        );
    }
}
