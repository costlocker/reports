<?php

namespace Costlocker\Reports\Clients;

use Costlocker\Reports\CostlockerClient;

class ClientsRevenuesProvider
{
    private $client;

    public function __construct(CostlockerClient $client = null)
    {
        $this->client = $client;
    }

    public function __invoke(\DateTime $lastDay)
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
                'state' => $this->determineProjectState($lastDay, $project),
                'revenue' => $project['revenue'],
                'expenses' => $this->client->sum(
                    $expenses[$project['id']] ?? [],
                    'buy'
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

        $report = new ClientsRevenuesReport();
        $report->lastDay = $lastDay;
        $report->clients = array_map(
            function (array $projects) use ($analyzeProjects) {
                $states = $this->client->map($projects, 'state');
                return [
                    'running' => $analyzeProjects($states['running'] ?? []),
                    'finished' => $analyzeProjects($states['finished'] ?? []),
                ];
            },
            $this->client->map($projects, 'client')
        );

        return $report;
    }

    public function determineProjectState(\DateTime $lastDay, array $project)
    {
        $dateStart = \DateTime::createFromFormat('Y-m-d', $project['da_start']);
        $dateEnd = $project['da_end'] ? \DateTime::createFromFormat('Y-m-d', $project['da_end']) : null;
        $firstDay = new \DateTime("{$lastDay->format('Y')}-01-01 00:00");

        if ($project['state'] == 'finished' && $dateEnd >= $firstDay && $dateEnd <= $lastDay) {
            return 'finished';
        }
        if ($project['state'] == 'running' && $dateStart >= $firstDay && $dateStart <= $lastDay) {
            return 'running';
        }

        return 'legacy';
    }
}
