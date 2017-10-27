<?php

namespace Costlocker\Reports\Client;

use GuzzleHttp\Client;
use Costlocker\Reports\CostlockerClient;

class HttpClient implements CostlockerClient
{
    private $client;

    public static function build($apiHost, $apiKey)
    {
        return new self(new Client([
            'base_uri' => $apiHost,
            'http_errors' => true,
            'auth' => ['costlocker/reports', $apiKey],
        ]));
    }

    public function __construct(Client $c)
    {
        $this->client = $c;
    }

    public function restApi($endpoint)
    {
        $response = $this->client->get(
            "/api-public/v2{$endpoint}"
        );
        return json_decode($response->getBody(), true);
    }

    public function request(array $request)
    {
        $response = $this->client->get(
            '/api-public/v1/',
            [
                'json' => $request,
            ]
        );
        return json_decode($response->getBody(), true);
    }

    public function map(array $rawData, $id)
    {
        $indexedItems = [];

        foreach ($rawData as $item) {
            $indexedItems[$item[$id]][] = $item;
        }

        return $indexedItems;
    }

    public function sum(array $rawData, $attribute)
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
