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
        foreach ($this->sortByName($this->people) as $personId => $person) {
            $projects = $this->getActiveProjects($personId);
            if ($projects) {
                $expandedProjects = $this->addProjectsNameAndClient($projects);
                $person['projects'] = $this->sortByName($expandedProjects);
                $people[$personId] = $person;
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

    private function addProjectsNameAndClient(array $projects)
    {
        foreach ($projects as $id => $project) {
            $projects[$id] = $project + [
                'name' => $this->getProjectName($id),
                'client' => $this->getProjectClient($id),
            ];
        }
        return $projects;
    }

    private function sortByName(array $items)
    {
        uasort(
            $items,
            function (array $first, array $second) {
                return strcmp($first['name'], $second['name']);
            }
        );

        return $items;
    }

    private function getProjectName($idProject)
    {
        return $this->projects[$idProject]['name'] ?? "#{$idProject}";
    }

    private function getProjectClient($idProject)
    {
        return $this->projects[$idProject]['client'] ?? "#{$idProject} client";
    }
}
