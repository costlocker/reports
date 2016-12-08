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
                    1 => ['first project'],
                    2 => ['second project'],
                ]
            ]
        ];
        $report->timesheet = [
            1 => [
                1 => 'irrelevant tracked hours',
            ],
        ];
        assertThat($report->getPersonProjects(1), arrayWithSize(1));
        assertThat($report->getPersonProjects(2), emptyArray());
    }

    public function testReturnAllProjectsWhenTimesheetIsEmpty()
    {
        $report = new CostlockerReport();
        $report->people = [
            1 => [
                'projects' => [
                    1 => ['first project'],
                    2 => ['second project'],
                ]
            ]
        ];
        assertThat($report->getPersonProjects(1), arrayWithSize(2));
    }

    public function testReturnNoProjectsForUnknownPerson()
    {
        $report = new CostlockerReport();
        $report->people = [
            1 => [
                'projects' => [
                    1 => ['first project'],
                ]
            ]
        ];
        $report->timesheet = [
            3 => [],
        ];
        assertThat($report->getPersonProjects(1), emptyArray());
        assertThat($report->getPersonProjects(2), emptyArray());
    }
}
