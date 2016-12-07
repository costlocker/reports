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

        $clients = $this->mapById($rawData['Simple_Clients']);
        $projects = [];

        foreach ($rawData['Simple_Projects'] as $project) {
            $projects[$project['id']] = [
                'name' => $project['name'],
                'client' => $clients[$project['client_id']]['name'],
            ];
        }

        return $projects;
    }

    private function mapById(array $rawData)
    {
        $indexedItems = [];

        foreach ($rawData as $item) {
            $indexedItems[$item['id']] = $item;
        }

        return $indexedItems;
    }
}
