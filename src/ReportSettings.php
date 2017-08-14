<?php

namespace Costlocker\Reports;

use KzykHys\CsvParser\CsvParser;

class ReportSettings
{
    const TRACKED_HOURS = 'Tracker';

    public $email;
    /** @var \Costlocker\Reports\Export\GoogleDrive */
    public $googleDrive;

    public $currency;
    public $personsSettings;
    public $exportSettings;
    public $filter;
    public $yearStart;
    /** @var \Symfony\Component\Console\Output\OutputInterface */
    public $output;

    private $persons;
    private $defaultPerson;

    public function getHoursSalary($person, $trackedHours = null)
    {
        $hours = $this->getPersonField($person, 'hours');
        return $hours == ReportSettings::TRACKED_HOURS ? $trackedHours : $hours;
    }

    public function getPosition($person)
    {
        return $this->getPersonField($person, 'position');
    }

    public function getAvailablePositions()
    {
        $this->parseCsvFile();
        $positions = [$this->defaultPerson['position']];
        foreach ($this->persons as $person) {
            $positions[] = $person['position'];
        }
        return array_values(array_unique(array_filter($positions)));
    }

    private function getPersonField($person, $field)
    {
        if ($this->personsSettings) {
            $this->parseCsvFile();
            return $this->persons[$person][$field] ?? $this->defaultPerson[$field];
        }
        return null;
    }

    private function parseCsvFile()
    {
        if ($this->defaultPerson) {
            return;
        }

        $this->defaultPerson = [
            'hours' => 0,
            'position' => null,
        ];
        $this->persons = [];

        if (!$this->personsSettings) {
            return;
        }

        foreach ($this->createCsvParser() as $index => $line) {
            $settings = [
                'hours' => $line[1],
                'position' => $line[2],
            ];
            if ($index == 0) {
                $this->defaultPerson = $settings;
            } else {
                $this->persons[$line[0]] = $settings;
            }
        }
    }

    private function createCsvParser()
    {
        $settings = ['encoding' => 'UTF-8'];
        return is_file($this->personsSettings)
            ? CsvParser::fromFile($this->personsSettings, $settings)
            : CsvParser::fromString($this->personsSettings, $settings);
    }
}
