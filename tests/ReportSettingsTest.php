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
        assertThat($settings->getPosition('Unknown'), is('Employee'));
        assertThat($settings->getPosition('Own TROK EZ5H'), is('Manager'));
    }

    /** @dataProvider provideDate */
    public function testPersonCanHaveDifferentSettingPerMonth($month, $expectedHours, $expectedPosition, $expectedRate)
    {
        $date = new \DateTime($month);
        $settings = new ReportSettings();
        $settings->personsSettings = __DIR__ . '/fixtures/persons.csv';
        assertThat($settings->getHoursSalary('Uncle Bob', 20, $date), is($expectedHours));
        assertThat($settings->getHourlyRate('Uncle Bob', $date), is($expectedRate));
        assertThat($settings->getPosition('Uncle Bob', $date), is($expectedPosition));
    }

    public function provideDate()
    {
        return [
            'the closest month' => ['2016-02-01', 20, 'Developer', 80],
            'date <= last month' => ['2017-07-01', 20, 'Developer', 100],
            'date > last month' => ['now', 160, 'Manager', null],
        ];
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
        $settings->personsSettings = file_get_contents(__DIR__ . '/fixtures/persons.csv');
        assertThat($settings->getAvailablePositions(), is(['Employee', 'Manager', 'Developer']));
    }
}
