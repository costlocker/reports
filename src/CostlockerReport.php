<?php

namespace Costlocker\Reports;

class CostlockerReport
{
    public $projects;
    public $people;
    public $timesheet;

    public function getPeople()
    {
        return $this->people;
    }

    public function getPersonProjects($personId)
    {
        $projects = $this->people[$personId]['projects'] ?? [];
        $projectsWithTrackedTime = array_filter(
            $projects,
            function (array $project) {
                return $project['hrs_tracked_month'] > 0;
            }
        );
        return $projectsWithTrackedTime ?: $projects;
    }

    public function getProjectName($idProject)
    {
        return $this->projects[$idProject]['name'];
    }

    public function getProjectClient($idProject)
    {
        return $this->projects[$idProject]['client'];
    }
}
