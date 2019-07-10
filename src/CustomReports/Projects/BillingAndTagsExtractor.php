<?php

namespace Costlocker\Reports\Custom\Projects;

use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\ReportSettings;

class BillingAndTagsExtractor extends Extractor
{
    public static function getConfig(): ExtractorBuilder
    {
        return ExtractorBuilder::buildFromJsonFile(__DIR__ . '/BillingAndTags.json')
            ->transformToXls(BillingAndTagsToXls::class);
    }

    public function __invoke(ReportSettings $s): array
    {
        $rawData = $this->client->request([
            'Simple_Projects' => new \stdClass(),
            'Simple_Clients' => new \stdClass(),
            'Simple_Tags' => new \stdClass(),
        ]);
        $projects = $this->client->map($rawData['Simple_Projects'], 'id');
        $clients = $this->client->map($rawData['Simple_Clients'], 'id');
        $tags = $this->client->map($rawData['Simple_Tags'], 'id');

        $result = [
            'projects' => [],
            'tags' => $this->transformTags($tags),
        ];
        foreach ($projects as $tempProjects) {
            foreach ($tempProjects as $project) {
                $projectTagIds = array_map(
                    function (array $tag) use ($tags) {
                        return $tag['id'];
                    },
                    $project['tags']
                );
                $projectTagNames = array_map(
                    function ($tagId) use ($result) {
                        return $result['tags'][$tagId];
                    },
                    $projectTagIds
                );
                sort($projectTagNames);

                $result['projects'][$project['id']] = [
                    'project_id' => $project['id'],
                    'project' => $project['name'],
                    'da_start' => \DateTime::createFromFormat('Y-m-d', $project['da_start']),
                    'da_end' => \DateTime::createFromFormat('Y-m-d', $project['da_end']),
                    'client_id' => $project['client_id'],
                    'client' => $clients[$project['client_id']][0]['name'],
                    'revenue' => $project['revenue'],
                    'tagNames' => implode(', ', $projectTagNames),
                    'tagIds' => $projectTagIds,
                    'billing' => [
                        'billedAmount' => 0,
                        'invoices' => [],
                    ],
                ];
            }
        }

        foreach ($this->billing() as $billing) {
            $result['projects'][$billing['project_id']]['billing']['billedAmount'] += $billing['amount'];
            $result['projects'][$billing['project_id']]['billing']['invoices'][] = [
                "id" => $billing['id'],
                "description" => $billing['name'],
                "date" => \DateTime::createFromFormat('Y-m-d', $billing['date']),
                "amount" => $billing['amount'],
                "is_sent" => $billing['issued'],
            ];
        }

        foreach ($result['projects'] as $id => $project) {
            if (!$project['billing']['invoices'] ||
                abs($project['revenue'] - $project['billing']['billedAmount']) > 0.1
            ) {
                $result['projects'][$id]['billing']['invoices'][] = [
                    "id" => 0,
                    "description" => "Remaining to be billed",
                    "date" => $project['da_end'],
                    "amount" => $project['revenue'] - $project['billing']['billedAmount'],
                    "is_sent" => false,
                ];
            }
        }

        return $result;
    }

    private function transformTags(array $tags)
    {
        $result = [];
        foreach ($tags as $tag) {
            $result[$tag[0]['id']] = $tag[0]['name'];
        }
        ksort($result);
        return $result;
    }

    private function billing()
    {
        $rawData = $this->client->request([
            'Simple_Projects_Billing' => new \stdClass(),
        ]);

        return $rawData['Simple_Projects_Billing'];
    }
}
