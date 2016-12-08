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
        if ($this->timesheet) {
            foreach (array_keys($projects) as $projectId) {
                if (!isset($this->timesheet[$personId][$projectId])) {
                    unset($projects[$projectId]);
                }
            }
        }
        return $projects;
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
