<?php

namespace Costlocker\Reports\Profitability;

class ProfitabilityReport
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
                'name' => $this->getProjectAttribute($id, 'name', "#{$id}"),
                'client' => $this->getProjectAttribute($id, 'client', "#{$id} client"),
                'tags' => $this->getProjectAttribute($id, 'tags', []),
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

    private function getProjectAttribute($idProject, $attribute, $default)
    {
        return $this->projects[$idProject][$attribute] ?? $default;
    }
}
