<?php

namespace Costlocker\Reports;

class ReportSettingsTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadPersonHoursFromCsvFile()
    {
        $settings = new ReportSettings();
        $settings->personsSettings = __DIR__ . '/fixtures/persons.csv';
        assertThat($settings->getHoursSalary('Unknown'), is(160));
        assertThat($settings->getHoursSalary('Own TROK EZ5H'), is(120));
        assertThat($settings->getHoursSalary('Uncle Bob', 20), is(20));
        assertThat($settings->getPosition('Unknown'), is('Employee'));
        assertThat($settings->getPosition('Own TROK EZ5H'), is('Manager'));
        assertThat($settings->getPosition('Uncle Bob', 20), is('Developer'));
    }

    public function testByDefaultNoHoursAreLoaded()
    {
        $settings = new ReportSettings();
        assertThat($settings->getHoursSalary('Unknown'), is(nullValue()));
        assertThat($settings->getPosition('Unknown'), is(nullValue()));
        assertThat($settings->getAvailablePositions(), is(emptyArray()));
    }

    public function testGetAllAvailablePositions()
    {
        $settings = new ReportSettings();
        $settings->personsSettings = __DIR__ . '/fixtures/persons.csv';
        assertThat($settings->getAvailablePositions(), is(['Employee', 'Manager', 'Developer']));
    }
}
