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

    public function getPersonProjects(array $person)
    {
        return $person['projects'];
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
