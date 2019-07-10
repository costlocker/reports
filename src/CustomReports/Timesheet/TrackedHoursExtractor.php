<?php

namespace Costlocker\Reports\Custom\Timesheet;

use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\ReportSettings;

class TrackedHoursExtractor extends Extractor
{
    public static function getConfig(): ExtractorBuilder
    {
        return ExtractorBuilder::buildFromJsonFile(__DIR__ . '/TrackedHours.json')
            ->transformToXls(TrackedHoursToXls::class);
    }

    public function __invoke(ReportSettings $s): array
    {
        $month = $s->date;

        $data = $this->client->request([
            'Simple_People' => new \stdClass(),
            'Simple_Projects' => new \stdClass(),
            'Simple_Clients' => new \stdClass(),
            'Simple_Activities' => new \stdClass(),
            'Simple_Overheads' => [
                'type' => ['person_bonus']
            ],
        ]);
        $people = $this->client->map($data['Simple_People'], 'id');
        $projects = $this->client->map($data['Simple_Projects'], 'id');
        $clients = $this->client->map($data['Simple_Clients'], 'id');
        $activities = $this->client->map($data['Simple_Activities'], 'id');
        $bonuses = $this->client->map($data['Simple_Overheads'], 'person_id');
            
        $results = [];
        foreach ($this->getAggregatedMonthlyTimesheet($month) as $personId => $timesheet) {
            foreach ($timesheet as $projectId => list($trackedHours, $trackedActivities)) {
                $person = $people[$personId][0];
                $project = $projects[$projectId][0];
                $workunitActiviites = [];
                foreach ($trackedActivities as $id => $tracked) {
                    $workunitActiviites[$activities[$id][0]['name'] ?? '-'] = $tracked;
                }
                $results[] = [
                    'person' => "{$person['first_name']} {$person['last_name']}",
                    'person_bonus_id' => $bonuses[$personId][0]['name'] ?? null,
                    'project' => $project['name'],
                    'project_id' => $project['jobid'],
                    'client' => $clients[$project['client_id']][0]['name'],
                    'hrs_tracked_month' => $trackedHours,
                    'activities' => $workunitActiviites,
                ];
            }
        }
        return [
            'month' => $month,
            'people' => $results,
        ];
    }

    private function getAggregatedMonthlyTimesheet(\DateTime $month)
    {
        $rawData = $this->client->request([
            'Simple_Timesheet' => [
                'datef' => $month->format('Y-m-01'),
                'datet' => $month->format('Y-m-t'),
            ],
        ]);
        return array_map(
            function (array $personSheet) {
                return array_map(
                    function ($projectSheet) {
                        $activities = array_map(
                            function ($activitySheet) {
                                return $this->calculateTrackedHours($activitySheet);
                            },
                            $this->client->map($projectSheet, 'activity')
                        );
                        return [$this->calculateTrackedHours($projectSheet), $activities];
                    },
                    $this->client->map($personSheet, 'project')
                );
            },
            $this->client->map($rawData['Simple_Timesheet'], 'person')
        );
    }

    private function calculateTrackedHours(array $sheet)
    {
        $trackedSeconds = $this->client->sum($sheet, 'interval');
        return $trackedSeconds / 3600;
    }
}
