<?php

namespace Costlocker\Reports\Config;

use Costlocker\Reports\ReportsRegistry;
use Costlocker\Reports\Custom\Projects\BillingAndTagsExtractor;

class ParseConfigTest extends \PHPUnit\Framework\TestCase
{
    const REPORT_TYPE = 'Projects.BillingAndTags';
    private $validJson = [
        'costlocker' => [
            'host' => 'https://new.costlocker.com',
            'tokens' => ['personal access token with 40 characters'],
        ],
        'reportType' => self::REPORT_TYPE,
        'config' => [
            'title' => 'test',
            'dateRange' => Enum::DATE_RANGE_ALLTIME,
        ],
        'customConfig' => [],
        'export' => [
            'filename' => 'test filename',
            'email' => 'john@example.com',
            'googleDrive' => [
                'folderId' => 'test',
                'files' => [],
            ],
        ],
    ];
    private $exportDir = '/irrelevant-path';

    /** @dataProvider provideDateRange */
    public function testDateRange(array $config, $expectedDates, $expectedTitle, $expectedUniqueId = '')
    {
        list($isInvalid, $settings) = $this->processJson(['config' => $config]);
        assertThat($isInvalid, is(false));
        $normalizedDates = array_map(
            function (\DateTime $d) {
                return $d->format('Y-m-d');
            },
            $settings['dates']
        );
        assertThat($normalizedDates, is($expectedDates));
        assertThat($settings['title'], is($expectedTitle));
        assertThat($settings['export']['googleDrive']['uniqueReportId'], is($expectedUniqueId));
    }

    public function provideDateRange()
    {
        $const = 'strval';
        return [
            Enum::DATE_RANGE_ALLTIME => [
                [
                    'title' => 'test {YEAR}',
                    'dateRange' => Enum::DATE_RANGE_ALLTIME,
                ],
                emptyArray(),
                'test {YEAR}', // placeholder is never replaced for ALLTIME date range
                Enum::DATE_RANGE_ALLTIME,
            ],
            Enum::DATE_RANGE_WEEK => [
                [
                    'title' => '{YEAR} - week #{WEEK} ({FIRST(d.m.Y)} - {LAST(Y-m-d)})',
                    'dateRange' => Enum::DATE_RANGE_WEEK,
                ],
                [
                    date('Y-m-d', strtotime('monday last week')),
                    // end date is available for expading title, but it's not returned (week would be generated twice)
                ],
                "{$const(date('Y'))} - week #{$const(date('W', strtotime('monday last week')))} "
                . "({$const(date('d.m.Y', strtotime('monday last week')))} - "
                . "{$const(date('Y-m-d', strtotime('sunday last week')))})",
                'week-' . date('Y-m-d', strtotime('monday last week')),
            ],
            Enum::DATE_RANGE_WEEK . ' - custom day' => [
                [
                    'title' => '2019 - week #{WEEK} ({FIRST(d.m.Y)} - {LAST(Y-m-d)})',
                    'dateRange' => Enum::DATE_RANGE_WEEK,
                    'customDates' => ['2019-05-01'],
                ],
                [
                    '2019-04-29',
                ],
                "2019 - week #18 (29.04.2019 - 2019-05-05)",
                'week-2019-04-29',
            ],
            Enum::DATE_RANGE_LAST_7_DAYS => [
                [
                    'title' => '{FIRST(d.m.Y)} {LAST(Y-m-d)}',
                    'dateRange' => Enum::DATE_RANGE_LAST_7_DAYS,
                ],
                [date('Y-m-d', strtotime('now - 7 days'))],
                "{$const(date('d.m.Y', strtotime('now - 7 days')))} {$const(date('Y-m-d', strtotime('now - 1 day')))}",
                json_encode([date('Y-m-d', strtotime('now - 7 days')), date('Y-m-d', strtotime('now - 1 day'))]),
            ],
            Enum::DATE_RANGE_LAST_7_DAYS . ' - custom day' => [
                [
                    'title' => '{FIRST(d.m.Y)} {LAST(Y-m-d)}',
                    'dateRange' => Enum::DATE_RANGE_LAST_7_DAYS,
                    'customDates' => ['2019-08-18'],
                ],
                ['2019-08-12'],
                "12.08.2019 2019-08-18",
                json_encode(['2019-08-12', '2019-08-18']),
            ],
            Enum::DATE_RANGE_YEAR => [
                [
                    'title' => 'test year without future months',
                    'dateRange' => Enum::DATE_RANGE_YEAR,
                ],
                array_map(
                    function ($monthNumber) {
                        $year = date('m') > 1 ? date('Y') : (date('Y') - 1);
                        $month = str_pad($monthNumber, 2, '0', STR_PAD_LEFT);
                        return "{$year}-{$month}-01";
                    },
                    date('m') > 1 ? range(1, date('m') - 1) : range(1, 12)
                ),
                'test year without future months',
                'year-' . (date('m') > 1 ? date('Y') : (date('Y') - 1)),
            ],
            Enum::DATE_RANGE_MONTHS => [
                [
                    'title' => '{FIRST(Y-m-d)} - {LAST(Y-m-d)}',
                    'dateRange' => Enum::DATE_RANGE_MONTHS,
                    'customDates' => ['2018-04-29', '2018-06-05'],
                ],
                [
                    '2018-04-01',
                    '2018-05-01',
                    '2018-06-01',
                ],
                "2018-04-01 - 2018-06-01",
                json_encode(['2018-04-01', '2018-05-01', '2018-06-01']),
            ],
        ];
    }

    public function testLoadCustomConfig()
    {
        list($isInvalid, $settings) = $this->processJson([
            'customConfig' => [
                ['key' => 'json.encoded', 'format' => 'json', 'value' => json_encode(['key' => 'value'])],
                ['key' => 'json.array', 'format' => 'json', 'value' => ['key' => 'value']],
                ['key' => 'json.object', 'format' => 'json', 'value' => (object) ['key' => 'value']],
                ['key' => 'myTitle', 'format' => 'text', 'value' => 'Internal title'],
            ],
        ]);
        assertThat($isInvalid, is(false));
        assertThat($settings['customConfig'], is([
            'json.encoded' => ['key' => 'value'],
            'json.array' => ['key' => 'value'],
            'json.object' => ['key' => 'value'],
            'myTitle' => 'Internal title',
        ]));
    }

    public function testDetectInvalidJson()
    {
        list($isInvalid, $errors) = $this->processJson([
            'reportType' => 'invalid',
            'costlocker' => [
                'host' => 'not an url',
                'tokens' => ['short token']
            ],
            'config' => [
                'title' => null,
                'dateRange' => 'invalid',
                'currency' => 'unknown',
                'customDates' => [
                    'invalid',
                ],
                'format' => 'txt',
            ],
            'customConfig' => [
                ['format' => 'text', 'value' => 'missing key']
            ],
            'export' => [
                'filename' => 'file-cant-have.extension',
                'email' => 'invalid',
                'googleDrive' => [
                    'files' => 'invalid files DB',
                ],
            ],
        ]);
        assertThat($isInvalid, is(true));
        assertThat($errors, is(arrayWithSize(12)));
    }

    public function testLoadETL()
    {
        list($isInvalid, $settings) = $this->processJson();
        assertThat($isInvalid, is(false));
        assertThat($settings['ETL'], is(nonEmptyArray()));
    }

    public function testNormalizeFilename()
    {
        list($isInvalid, $settings) = $this->processJson([
            'export' => [
                'filename' => '  test/ year ',
            ],
        ]);
        assertThat($isInvalid, is(false));
        assertThat($settings['export']['filesystem'], is("{$this->exportDir}/test-year"));
    }

    private function processJson(array $data = [])
    {
        $report = array_replace_recursive($this->validJson, $data);
        $registry = new ReportsRegistry([BillingAndTagsExtractor::class], []);
        $uc = new ParseConfig($registry, $this->exportDir);
        return $uc($report);
    }
}
