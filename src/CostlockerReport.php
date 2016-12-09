<?php

namespace Costlocker\Reports;

class CostlockerReport
{
    public $selectedMonth;
    public $projects;
    public $people;
    public $timesheet;

    public function getActivePeople()
    {
        $people = [];
        foreach ($this->people as $personId => $person) {
            $projects = $this->getActiveProjects($personId);
            if ($projects) {
                $people[$personId] = ['projects' => $projects] + $person;
            }
        }
        return $people ?: $this->people;
    }

    private function getActiveProjects($personId)
    {
        return array_filter(
            $this->people[$personId]['projects'] ?? [],
            function (array $project) {
                return $project['hrs_tracked_month'] > 0;
            }
        );
    }

    public function getProjectName($idProject)
    {
        return $this->projects[$idProject]['name'] ?? "#{$idProject}";
    }

    public function getProjectClient($idProject)
    {
        return $this->projects[$idProject]['client'] ?? "#{$idProject} client";
    }
}
