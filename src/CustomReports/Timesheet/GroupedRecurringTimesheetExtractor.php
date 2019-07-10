<?php

namespace Costlocker\Reports\Custom\Timesheet;

use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\ReportSettings;

class GroupedRecurringTimesheetExtractor extends Extractor
{
    public static function getConfig(): ExtractorBuilder
    {
        return ExtractorBuilder::buildFromJsonFile(__DIR__ . '/GroupedRecurringTimesheet.json')
            ->transformToXls(GroupedRecurringTimesheetToXls::class);
    }

    public function __invoke(ReportSettings $s): array
    {
        $month = $s->date;

        $recurringId = $s->customConfig['recurringProjectId'];
        if (!$recurringId || !is_numeric($recurringId)) {
            throw new \RuntimeException('--filter must contain recurring project id');
        }
        $allocatedHours = $s->customConfig['allocatedActivityHours'];

        $data = $this->client->request([
            'Simple_People' => new \stdClass(),
            'Simple_Activities' => new \stdClass(),
        ]);
        $people = $this->client->map($data['Simple_People'], 'id');
        $activities = $this->client->map($data['Simple_Activities'], 'id');

        $results = [];
        $projectIds = [];
        foreach ($this->getAggregatedMonthlyTimesheet($month, $recurringId) as $activityId => $activitySheet) {
            $activityName = $activities[$activityId][0]['name'];
            foreach ($activitySheet as $personId => $personSheet) {
                $person = $people[$personId][0];
                foreach ($personSheet as list($week, $day, $description, $scsTracked, $projectId)) {
                    $projectIds[$projectId] = $projectId;
                    if (!array_key_exists($activityName, $results)) {
                        $results[$activityName] = $this->initActivityStructure(
                            $month,
                            $allocatedHours[$activityId] ?? 0
                        );
                    }
                    $results[$activityName]['people'][] = "{$person['first_name']} {$person['last_name']}";
                    $results[$activityName]['weeks'][$week][$day]['descriptions'][] = $description;
                    $results[$activityName]['weeks'][$week][$day]['scs_tracked'] += $scsTracked;
                }
            }
        }
        return [
            'month' => $month,
            'activities' => $results,
            'recurring' => $this->loadProjects($recurringId, $projectIds),
        ];
    }

    private function initActivityStructure(\DateTime $month, $dailyHours)
    {
        $result = [
            'daily_hours' => $dailyHours,
            'people' => [],
            'weeks' => [],
        ];
        for ($day = 1; $day <= $month->format('t'); $day++) {
            $date = \DateTime::createFromFormat('Y-m-d', $month->format("Y-m-{$day}"));
            $week = $date->format('W');
            if (!array_key_exists($week, $result['weeks'])) {
                $result['weeks'][$week] = [];
            }
            $isWeekend = $date->format('N') >= 6;
            $isHoliday = CzechHolidays::isHoliday($date);
            $result['weeks'][$week][$day] = [
                'descriptions' => [],
                'scs_tracked' => 0,
                'is_weekend' => $isWeekend,
                'is_holiday' => $isHoliday,
                'is_working_day' => !$isWeekend && !$isHoliday,
            ];
        }
        return $result;
    }

    private function getAggregatedMonthlyTimesheet(\DateTime $month, $recurringId)
    {
        $rawData = $this->client->request([
            'Simple_Timesheet' => [
                'datef' => $month->format('Y-m-01'),
                'datet' => $month->format('Y-m-t'),
                'recurring' => [$recurringId],
            ],
        ]);
        return array_map(
            function (array $activitySheet) {
                return array_map(
                    function ($personSheet) {
                        $data = [];
                        foreach ($personSheet as $entry) {
                            $date = \DateTime::createFromFormat('Y-m-d', substr($entry['date'], 0, 10));
                            $data[] = [
                                $date->format('W'),
                                $date->format('j'),
                                $entry['description'],
                                $entry['interval'],
                                $entry['project']
                            ];
                        }
                        return $data;
                    },
                    $this->client->map($activitySheet, 'person')
                );
            },
            $this->client->map($rawData['Simple_Timesheet'], 'activity')
        );
    }

    private function loadProjects($recurringId, array $projectIds)
    {
        $apiProjects = $this->client->request([
            'Simple_Projects' => [
                'recurring' => true,
                'project' => array_merge([$recurringId], $projectIds),
            ],
        ]);
        $getFirstProject = function (array $projects) {
            if (!$projects) {
                return [
                    'id' => null,
                    'name' => '???',
                ];
            }
            $normalized = array_map(
                function (array $p) {
                    return [
                        'id' => $p['id'],
                        'name' => $p['name'],
                    ];
                },
                $projects
            );
            return reset($normalized);
        };
        $projects = $this->client->map($apiProjects['Simple_Projects'], 'project_type');

        return [
            'template' => $getFirstProject($projects['recurring'] ?? []),
            'instance' => $getFirstProject($projects['standard'] ?? []),
        ];
    }
}
