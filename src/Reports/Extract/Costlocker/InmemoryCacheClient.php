<?php

namespace Costlocker\Reports\Extract\Costlocker;

class InmemoryCacheClient implements CostlockerClient
{
    private $client;
    private $cache = [];

    public function __construct(CostlockerClient $c)
    {
        $this->client = $c;
    }

    public function request(array $request)
    {
        // If necessary, file cached client can be reverted
        // https://github.com/costlocker/reports/blob/v2.0.0/src/Client/CachedClient.php
        $requestId = md5(json_encode($request));
        if (!array_key_exists($requestId, $this->cache)) {
            $this->cache[$requestId] = $this->client->request($request);
        }
        return $this->cache[$requestId];
    }

    public function restApi($endpoint)
    {
        return $this->client->restApi($endpoint);
    }

    public function map(array $rawData, $id)
    {
        return $this->client->map($rawData, $id);
    }

    public function sum(array $rawData, $attribute)
    {
        return $this->client->sum($rawData, $attribute);
    }
}
