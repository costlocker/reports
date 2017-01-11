<?php

namespace Costlocker\Reports;

use KzykHys\CsvParser\CsvParser;

class ReportSettings
{
    const TRACKED_HOURS = 'Tracker';

    public $email;
    public $hardcodedHours;
    /** @var \Symfony\Component\Console\Output\OutputInterface */
    public $output;

    public function getHoursSalary($person, $trackedHours = null)
    {
        if ($this->hardcodedHours) {
            static $defaultHours = null, $persons = [];
            if (!$persons) {
                list($defaultHours, $persons) = $this->parseCsvFile();
            }
            $hours = $persons[$person] ?? $defaultHours;
            return $hours == ReportSettings::TRACKED_HOURS ? $trackedHours : $hours;
        }
        return null;
    }

    private function parseCsvFile()
    {
        $defaultHours = null;
        $persons = [];
        foreach (CsvParser::fromFile($this->hardcodedHours, ['encoding' => 'UTF-8']) as $index => $line) {
            if ($index == 0) {
                $defaultHours = $line[1];
            } else {
                $persons[$line[0]] = $line[1];
            }
        }
        return [$defaultHours, $persons];
    }
}
