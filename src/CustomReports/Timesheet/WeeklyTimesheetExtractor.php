<?php

namespace Costlocker\Reports\Custom\Timesheet;

use Costlocker\Reports\Config\Dates;
use Costlocker\Reports\Extract\Extractor;
use Costlocker\Reports\Extract\ExtractorBuilder;
use Costlocker\Reports\ReportSettings;

class WeeklyTimesheetExtractor extends Extractor
{
    public static function getConfig(): ExtractorBuilder
    {
        return ExtractorBuilder::buildFromJsonFile(__DIR__ . '/WeeklyTimesheet.json')
            ->transformToXls(WeeklyTimesheetToXls::class)
            ->transformToHtml(__DIR__ . '/WeeklyTimesheetToHtml.twig');
    }

    public function __invoke(ReportSettings $s): array
    {
        $firstDay = $s->date;
        $lastDay = clone $s->date;
        $lastDay->modify('+ 6 days');

        $data = $this->client->request([
            'Simple_People' => new \stdClass(),
            'Simple_Projects' => new \stdClass(),
            'Simple_Clients' => new \stdClass(),
            'Simple_Activities' => new \stdClass(),
            'Simple_Groups' => new \stdClass(),
        ]);
        $people = $this->client->map($data['Simple_People'], 'id');
        $projects = $this->client->map($data['Simple_Projects'], 'id');
        $clients = $this->client->map($data['Simple_Clients'], 'id');
        $activities = $this->client->map($data['Simple_Activities'], 'id');
        $peopleAndGroups = $this->parseGroups($data['Simple_Groups'], $s->customConfig['groupNames']);

        $timesheet = $this->client->request([
            'Simple_Timesheet' => [
                'datef' => $firstDay->format('Y-m-d'),
                'datet' => $lastDay->format('Y-m-d'),
                'nonproject' => true,
                'person' => array_keys($peopleAndGroups),
            ],
        ]);

        $results = [];
        foreach ($timesheet['Simple_Timesheet'] as $row) {
            $person = $people[$row['person']][0];
            $project = $row['project'] ? $projects[$row['project']][0] : [
                'name' => null,
                'client_id' => null,
            ];
            $results[] = [
                'date' => new \DateTime($row['date']),
                'project' => $project['name'],
                'client' => $project['client_id'] ? $clients[$project['client_id']][0]['name'] : null,
                'person' => "{$person['first_name']} {$person['last_name']}",
                'activity' => $row['activity'] ? $activities[$row['activity']][0]['name'] : null,
                'scs_tracked' => $row['interval'],
                'team' => $peopleAndGroups[$person['id']],
            ];
        }
        return [
            'entries' => $results,
        ];
    }

    private function parseGroups(array $tenantGroups, array $selectedGroups)
    {
        $peopleAndGroups = [];
        foreach ($tenantGroups as $group) {
            if (!in_array($group['name'], $selectedGroups)) {
                continue;
            }
            foreach ($group['person'] as $personId) {
                $peopleAndGroups[$personId] = $group['name'];
            }
        }
        return $peopleAndGroups;
    }
}
