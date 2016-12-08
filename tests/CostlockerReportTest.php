<?php

namespace Costlocker\Reports;

class CostlockerReportTest extends \PHPUnit_Framework_TestCase
{
    public function testFilterOnlyProjectsWithTrackedTime()
    {
        $report = new CostlockerReport();
        $report->people = [
            1 => [
                'projects' => [
                    1 => ['hrs_tracked_month' => 10],
                    2 => ['hrs_tracked_month' => 0],
                ]
            ]
        ];
        assertThat($report->getPersonProjects(1), arrayWithSize(1));
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
        assertThat($report->getPersonProjects(1), arrayWithSize(2));
    }
}
