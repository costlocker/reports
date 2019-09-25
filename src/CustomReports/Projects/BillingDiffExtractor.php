<?php

namespace Costlocker\Reports\Custom\Projects;

use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\ReportSettings;

class BillingDiffExtractor extends Extractor
{
    public static function getConfig(): ExtractorBuilder
    {
        return ExtractorBuilder::buildFromJsonFile(__DIR__ . '/BillingDiff.json')
            ->transformToXls(BillingDiffToXls::class);
    }

    public function __invoke(ReportSettings $s): array
    {
        // it detects changes, so `Remaining to be billed` is not necessary (it wouldn't be returned by webhook)
        list($previousBilling, $currentBilling) = $this->loadBilling($s);
        $diff = $this->compareBilling($previousBilling, $currentBilling);
        return [
            'dateChange' => new \DateTime(),
            'billing' => $this->transformDiff($diff, $s),
        ];
    }

    private function loadBilling(ReportSettings $s)
    {
        $rawBilling = $this->client->request([
            'Simple_Projects_Billing' => new \stdClass(),
        ]);
        $currentBilling = $this->mapBilling($rawBilling);
        $previousBilling = $currentBilling;
        if (file_exists($s->customConfig['Simple_Projects_Billing-filepath'])) {
            $previousBilling = $this->mapBilling(
                json_decode(file_get_contents($s->customConfig['Simple_Projects_Billing-filepath']), true)
            );
        }
        file_put_contents(
            $s->customConfig['Simple_Projects_Billing-filepath'],
            json_encode($rawBilling, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        return [$previousBilling, $currentBilling];
    }

    private function mapBilling(array $json)
    {
        $billing = [];
        foreach ($json['Simple_Projects_Billing'] as $b) {
            $billing[$b['id']] = $b;
        }
        return $billing;
    }

    private function compareBilling(array $old, array $new)
    {
        $result = [
            'billing' => [],
            'projects' => [],
        ];

        $createdIds = array_values(array_diff(array_keys($new), array_keys($old)));
        foreach ($createdIds as $id) {
            $result['billing'][$id] = [
                'action' => 'create',
                'before' => [],
                'after' => $new[$id],
                'diff' => $new[$id],
            ];
            $result['projects'][$new[$id]['project_id']] = $new[$id]['project_id'];
        }
        
        $deletedIds = array_values(array_diff(array_keys($old), array_keys($new)));
        foreach ($deletedIds as $id) {
            $result['billing'][$id] = [
                'action' => 'delete',
                'before' => $old[$id],
                'after' => [],
                'diff' => $old[$id],
            ];
            $result[$old[$id]['project_id']] = $old[$id]['project_id'];
        }

        $keysMapping = [
            'name' => 'description',
            'issued' => 'is_invoiced',
        ];
        foreach ($old as $oldBilling) {
            $id = $oldBilling['id'];
            if (in_array($id, $deletedIds)) {
                continue;
            }
            $newBilling = $new[$id];
            $differentKeys = array_keys(array_diff($newBilling, $oldBilling));
            if (!$differentKeys) {
                continue;
            }
            $diff = [];
            $prettyKeys = [];
            foreach ($differentKeys as $key) {
                $diff[$key] = $newBilling[$key];
                $prettyKeys[] = $keysMapping[$key] ?? $key;
            }
            $result['billing'][$id] = [
                'action' => 'update ' . implode(', ', $prettyKeys),
                'before' => $oldBilling,
                'after' => $newBilling,
                'diff' => $diff,
            ];
            $result['projects'][$oldBilling['project_id']] = $oldBilling['project_id'];
        }
        return $result;
    }

    private function transformDiff(array $diff, ReportSettings $settings)
    {
        $rawData = $this->client->request([
            'Simple_Projects' => [
                'project' => $diff['projects'],
            ],
            'Simple_Clients' => new \stdClass(),
        ]);
        $projects = $this->client->map($rawData['Simple_Projects'], 'id');
        $clients = $this->client->map($rawData['Simple_Clients'], 'id');

        $convertBilling = function (array $b) {
            return [
                'description' => $b['name'] ?? null,
                'date' => ($b['date'] ?? null)
                    ? \DateTime::createFromFormat('Y-m-d', $b['date']) : null,
                'amount' => $b['amount'] ?? null,
                'is_sent' => $b['issued'] ?? null,
            ];
        };

        $result = [];
        foreach ($diff['billing'] as $id => $b) {
            $projectId = $b['after']['project_id'] ?? $b['before']['project_id'];
            $project = $projects[$projectId][0];
            $result[] = [
                'id' => $id,
                'project_id' => $projectId,
                'project' => $project['name'],
                'client' => $clients[$project['client_id']][0]['name'],
                'action' => $b['action'],
                'before' => $convertBilling($b['before']),
                'after' => $convertBilling($b['after']),
                'diff' => $convertBilling($b['diff']),
                'url' => $settings->costlocker->url([
                    'path' => "/projects/detail/{$projectId}/billing",
                    'project_id' => $projectId,
                ])
            ];
        }
        return $result;
    }
}
