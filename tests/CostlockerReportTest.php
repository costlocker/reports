<?php

namespace Costlocker\Reports;

class CostlockerReportTest extends \PHPUnit_Framework_TestCase
{
    public function testFilterOnlyPeopleAndProjectsWithTrackedTime()
    {
        $report = new CostlockerReport();
        $report->people = [
            1 => [
                'projects' => [
                    1 => ['hrs_tracked_month' => 10],
                    2 => ['hrs_tracked_month' => 0],
                ]
            ],
            2 => [
                'projects' => []
            ],
        ];
        $people = $report->getActivePeople();
        assertThat($people, arrayWithSize(1));
        assertThat($people[1]['projects'], arrayWithSize(1));
    }

    public function testReturnAllProjectsWhenTimesheetIsEmpty()
    {
        $report = new CostlockerReport();
        $report->people = [
            1 => [
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

    public function testFallbackForUnknownProject()
    {
        $report = new CostlockerReport();
        assertThat($report->getProjectName(1), is('#1'));
        assertThat($report->getProjectClient(1), is('#1 client'));
    }
}
