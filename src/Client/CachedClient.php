<?php

namespace Costlocker\Reports\Client;

use Costlocker\Reports\CostlockerClient;

class CachedClient implements CostlockerClient
{
    private $client;
    private $cacheDir;
    private $auth;
    private $output;

    public function __construct(CostlockerClient $c, $cacheDir, $auth, $output)
    {
        $this->client = $c;
        $this->cacheDir = $cacheDir;
        $this->auth = $auth;
        $this->output = $output;
    }

    public function request(array $request)
    {
        $filename = md5($this->auth) . '-' . md5(json_encode($request)) . '.json';
        $path = "{$this->cacheDir}/{$filename}";
        if (!is_file($path)) {
            $this->output->__invoke("Download request ({$this->auth}): " . json_encode($request));
            $response = $this->client->request($request);
            file_put_contents($path, json_encode($response));
        } else {
            $this->output->__invoke("Load from cache ({$this->auth}): " . json_encode($request));
        }
        return json_decode(file_get_contents($path), true);
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
