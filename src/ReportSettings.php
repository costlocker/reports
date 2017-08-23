<?php

namespace Costlocker\Reports;

use KzykHys\CsvParser\CsvParser;

class ReportSettings
{
    const TRACKED_HOURS = 'Tracker';

    public $email;
    /** @var \Costlocker\Reports\Export\GoogleDrive */
    public $googleDrive;
    /** @var callable projectId => url */
    public $generateProjectUrl;

    public $currency;
    public $personsSettings;
    public $exportSettings;
    public $filter;
    public $yearStart;
    /** @var \Symfony\Component\Console\Output\OutputInterface */
    public $output;

    private $persons;
    private $defaultPerson;

    public function getHoursSalary($person, $trackedHours = null, \DateTime $month = null)
    {
        $hours = $this->getPersonField($person, 'hours', $month);
        return $hours == ReportSettings::TRACKED_HOURS ? $trackedHours : $hours;
    }

    public function getHourlyRate($person, \DateTime $month = null)
    {
        return $this->getPersonField($person, 'hourlyRate', $month);
    }

    public function getPosition($person, \DateTime $month = null)
    {
        return $this->getPersonField($person, 'position', $month);
    }

    public function getAvailablePositions()
    {
        $this->parseCsvFile();
        $positions = [$this->defaultPerson['position']];
        foreach ($this->persons as $person) {
            foreach ($person['positions'] as $position) {
                $positions[] = $position;
            }
        }
        return array_values(array_unique(array_filter($positions)));
    }

    private function getPersonField($person, $field, \DateTime $month = null)
    {
        if ($this->personsSettings) {
            $this->parseCsvFile();
            $settings = $this->getPersonSettings($person, $month);
            return $settings[$field];
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
            'hourlyRate' => null,
        ];
        $this->persons = [];

        if (!$this->personsSettings) {
            return;
        }

        foreach ($this->createCsvParser() as $index => $line) {
            if (count($line) < 3) {
                continue;
            }
            $settings = [
                'hours' => $line[1],
                'position' => $line[2],
                'hourlyRate' => $line[4] ?? null,
            ];
            if ($index == 0) {
                $this->defaultPerson = $settings;
            } else {
                $person = $line[0];
                if (!array_key_exists($person, $this->persons)) {
                    $this->persons[$person] = [
                        'current' => null,
                        'months' => [],
                        'positions' => [],
                    ];
                }
                if (isset($line[3]) && $line[3]) {
                    $date = new \DateTime($line[3]);
                    $this->persons[$person]['months'][$date->format('Ym')] = $settings;
                } else {
                    $this->persons[$person]['current'] = $settings;
                }
                $this->persons[$person]['positions'][] = $settings['position'];
            }
        }
    }

    private function getPersonSettings($person, \DateTime $reportMonth = null)
    {
        if (!array_key_exists($person, $this->persons)) {
            return $this->defaultPerson;
        }
        $settings = $this->persons[$person];
        if (!$reportMonth) {
            return $settings['current'];
        }
        foreach ($settings['months'] as $personMonth => $monthSettings) {
            if ($reportMonth->format('Ym') <= $personMonth) {
                return $monthSettings;
            }
        }
        return $settings['current'];
    }

    private function createCsvParser()
    {
        $settings = ['encoding' => 'UTF-8'];
        return is_file($this->personsSettings)
            ? CsvParser::fromFile($this->personsSettings, $settings)
            : CsvParser::fromString($this->personsSettings, $settings);
    }
}
