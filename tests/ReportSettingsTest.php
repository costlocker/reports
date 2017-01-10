<?php

namespace Costlocker\Reports;

class ReportSettingsTest extends \PHPUnit_Framework_TestCase
{
    public function testLoadPersonHoursFromCsvFile()
    {
        $settings = new ReportSettings();
        $settings->hardcodedHours = __DIR__ . '/fixtures/hours.csv';
        assertThat($settings->getHoursSalary('Unknown'), is(160));
        assertThat($settings->getHoursSalary('Own TROK EZ5H'), is(120));
        assertThat($settings->getHoursSalary('Uncle Bob', 20), is(20));
    }

    public function testByDefaultNoHoursAreLoaded()
    {
        $settings = new ReportSettings();
        assertThat($settings->getHoursSalary('Unknown'), is(nullValue()));
    }
}
