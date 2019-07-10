<?php

namespace Costlocker\Reports\Extract\Costlocker;

class CostlockerUrlGenerator
{
    private $client;
    private $apiHost;

    public function __construct(CostlockerClient $c, $apiHost)
    {
        $this->client = $c;
        $this->apiHost = $apiHost;
    }

    public function projectUrl($projectId)
    {
        return $this->url([
            'path' => "/projects/detail/{$projectId}/overview",
            'project_id' => $projectId,
            'query' => [],
        ]);
    }

    public function url(array $config)
    {
        if (!$config['path']) {
            return null;
        }
        $companyId = isset($config['project_id']) ? $this->projectToCompanyId($config['project_id']) : null;
        $baseUrl = $this->apiHost . ($companyId ? "/p/{$companyId}" : '');
        $queryString = $config['query'] ?? false ? ('?' . http_build_query($config['query'])) : '';
        return "{$baseUrl}{$config['path']}{$queryString}";
    }

    public function projectToCompanyName($projectId)
    {
        return $this->client->getCompany($projectId)['name'];
    }

    public function projectToCompanyId($projectId)
    {
        return $this->client->getCompany($projectId)['id'];
    }
}
