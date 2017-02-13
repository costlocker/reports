<?php

namespace Costlocker\Reports;

use KzykHys\CsvParser\CsvParser;

class ReportSettings
{
    const TRACKED_HOURS = 'Tracker';

    public $email;
    public $currency;
    public $personsSettings;
    public $exportSettings;
    /** @var \Symfony\Component\Console\Output\OutputInterface */
    public $output;

    public function getHoursSalary($person, $trackedHours = null)
    {
        $hours = $this->getPersonField($person, 'hours');
        return $hours == ReportSettings::TRACKED_HOURS ? $trackedHours : $hours;
    }

    public function getPosition($person)
    {
        return $this->getPersonField($person, 'position');
    }

    private function getPersonField($person, $field)
    {
        if ($this->personsSettings) {
            static $default = null, $persons = [];
            if (!$persons) {
                list($default, $persons) = $this->parseCsvFile();
            }
            return $persons[$person][$field] ?? $default[$field];
        }
        return null;
    }

    private function parseCsvFile()
    {
        $default = [
            'hours' => 0,
            'position' => null,
        ];
        $persons = [];
        foreach (CsvParser::fromFile($this->personsSettings, ['encoding' => 'UTF-8']) as $index => $line) {
            $settings = [
                'hours' => $line[1],
                'position' => $line[2],
            ];
            if ($index == 0) {
                $default = $settings;
            } else {
                $persons[$line[0]] = $settings;
            }
        }
        return [$default, $persons];
    }
}
