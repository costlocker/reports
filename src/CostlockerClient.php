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

    public function experiment()
    {
        $response = $this->client->get(
            '/api-public/v1/',
            [
                'json' => [
                    'Simple_People' => new \stdClass(),
                    'Simple_Projects' => new \stdClass(),
                    'Simple_Clients' => new \stdClass(),
                ],
            ]
        );

        echo json_encode(json_decode($response->getBody(), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
