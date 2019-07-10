<?php

namespace Costlocker\Reports\Extract\Costlocker;

class CompositeClient implements CostlockerClient
{
    private $clients = [];
    private $projectsMapping = [];

    public function __construct(array $clients)
    {
        if (!$clients) {
            throw new \InvalidArgumentException('At least one client must be specified');
        }
        foreach ($clients as $c) {
            $this->addClient($c);
        }
    }

    private function addClient(CostlockerClient $c)
    {
        $company = $c->restApi('/me')['data']['company'] ?? ['id' => null, 'name' => null];
        $this->clients[$company['id']] = [
            'company' => $company,
            'client' => $c,
        ];
    }

    public function getFirstCompanyName()
    {
        return reset($this->clients)['company']['name'];
    }

    public function request(array $request)
    {
        $response = [];
        foreach ($this->clients as $c) {
            $filteredRequest = $this->filterProjects($request, $c['company']);
            $companyResponse = $c['client']->request($filteredRequest);
            $response = array_merge_recursive($response, $companyResponse);
            $this->savePojects($companyResponse['Simple_Projects'] ?? [], $c['company']);
        }
        return $response;
    }

    private function savePojects(array $projects, array $company)
    {
        foreach ($projects as $project) {
            $this->projectsMapping[$project['id']] = $company['id'];
        }
    }

    private function filterProjects(array $request, array $company)
    {
        if (count($this->clients) == 1) {
            return $request;
        }
        foreach ($request as $resource => $filters) {
            foreach ($filters as $key => $filter) {
                if ($key == 'project') {
                    $request[$resource][$key] = array_values(array_filter(
                        $filter,
                        function ($id) use ($company) {
                            return $this->getCompany($id)['id'] == $company['id'];
                        }
                    ));
                }
            }
        }
        return $request;
    }

    public function getCompany($projectId)
    {
        $companyId = $this->projectsMapping[$projectId] ?? null;
        return $this->clients[$companyId]['company'] ?? ['id' => null, 'name' => null];
    }

    public function restApi($endpoint)
    {
        throw new \RuntimeException('Not supported');
    }

    public function map(array $rawData, $id)
    {
        return reset($this->clients)['client']->map($rawData, $id);
    }

    public function sum(array $rawData, $attribute)
    {
        return reset($this->clients)['client']->sum($rawData, $attribute);
    }
}
