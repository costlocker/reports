<?php

namespace Costlocker\Reports\Profitability;

class ProfitabilityReportTest extends \PHPUnit_Framework_TestCase
{
    public function testFilterOnlyPeopleAndProjectsWithTrackedTime()
    {
        $report = new ProfitabilityReport();
        $report->projects = [
            1 => ['name' => 'Web'],
            2 => ['name' => 'Marketing'],
        ];
        $report->people = [
            1 => [
                'name' => 'Amy',
                'projects' => [
                    1 => ['hrs_tracked_month' => 10],
                    2 => ['hrs_tracked_month' => 0],
                ]
            ],
            2 => [
                'name' => 'Bob',
                'projects' => []
            ],
        ];
        $people = $report->getActivePeople();
        assertThat($people, arrayWithSize(1));
        assertThat($people[1]['projects'], arrayWithSize(1));
    }

    public function testReturnAllProjectsWhenTimesheetIsEmpty()
    {
        $report = new ProfitabilityReport();
        $report->people = [
            1 => [
                'name' => 'Amy',
                'projects' => [
                    1 => ['hrs_tracked_month' => 0],
                    2 => ['hrs_tracked_month' => 0],
                ]
            ]
        ];
        $people = $report->getActivePeople();
        assertThat($people, arrayWithSize(1));
        assertThat($people[1]['projects'], arrayWithSize(2));
    }

    public function testSortPeopleAndProjectsByName()
    {
        $report = new ProfitabilityReport();
        $report->projects = [
            1 => ['name' => 'Web'],
            2 => ['name' => 'Marketing'],
        ];
        $report->people = [
            1 => [
                'name' => 'Leo',
                'projects' => [
                    2 => ['hrs_tracked_month' => 1],
                ]
            ],
            2 => [
                'name' => 'Kat',
                'projects' => [
                    1 => ['hrs_tracked_month' => 1],
                ]
            ],
            3 => [
                'name' => 'Joe',
                'projects' => [
                    1 => ['hrs_tracked_month' => 1],
                    2 => ['hrs_tracked_month' => 1],
                ]
            ],
        ];
        $people = $report->getActivePeople();
        assertThat($this->getNames($people), is(['Joe', 'Kat', 'Leo']));
        assertThat($this->getNames($people[3]['projects']), is(['Marketing', 'Web']));
    }

    private function getNames(array $items)
    {
        return array_values(array_map(
            function (array $item) {
                return $item['name'];
            },
            $items
        ));
    }

    public function testFallbackNamesForUnknownProject()
    {
        $report = new ProfitabilityReport();
        $report->people = [
            1 => [
                'name' => 'Amy',
                'projects' => [
                    1 => ['hrs_tracked_month' => 1],
                ]
            ]
        ];
        $people = $report->getActivePeople();
        assertThat($people[1]['projects'][1]['name'], is('#1'));
        assertThat($people[1]['projects'][1]['client'], is('#1 client'));
    }
}
