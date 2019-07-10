<?php

namespace Costlocker\Reports\Custom\Company;

use Costlocker\Reports\Transform\TransformToXls;
use Costlocker\Reports\ReportSettings;
use Costlocker\Reports\Transform\XlsBuilder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class CompanyOverviewToXls extends TransformToXls
{
    private $worksheets = [];

    public function __invoke(array $report, ReportSettings $settings)
    {
        // XlsCellBuilder isn't immutable, so highlighting cell in first table would affect second table
        $buildYearsCells = function () {
            return array_map(
                function ($text) {
                    return $this->dateToCell(new \DateTime($text), NumberFormat::FORMAT_DATE_YYYYMMDD2);
                },
                [1 => 'now - 1 year', 2 => 'now - 2 years', 3 => 'now - 3 years']
            );
        };
        $this->addSourceData($report, $buildYearsCells());
        $xls = $this->createWorksheet('Overview');
        $this->analyzePeople($xls, $buildYearsCells());
        $this->analyzeClients($xls, $buildYearsCells());
        $this->analyzeProjects($xls, $buildYearsCells());
        $this->analyzeProjectExpenses($xls);
        $this->analyzeProjectBilling($xls);
        $this->analyzeTimesheet($xls, $report['timesheet']['filterDates'], $settings);
    }

    private function analyzePeople(XlsBuilder $xls, array $yearsCells)
    {
        $expectsPeople = function ($expectedType = null) {
            $activePeople = ['C' => $this->escape('YES')];
            if (!$expectedType) {
                return $activePeople;
            }
            return $activePeople + ['E' => $this->escape($expectedType)];
        };

        $xls
            ->addRow(['People', ''], 'b6ef8f')
            ->autosizeColumnsInCurrentRow()
            ->addRow([
                'Active people',
                $this->aggregate('People', $expectsPeople()),
            ])
            ->addRow([
                'Salary',
                $this->aggregate('People', $expectsPeople(CostlockerEnum::SALARY_TYPE)),
            ])
            ->addRow([
                'Hourly rate',
                $this->aggregate('People', $expectsPeople(CostlockerEnum::HOURLY_RATE_TYPE)),
            ])
            ->addRow([
                'Zero hourly rate',
                $this->aggregate('People', $expectsPeople() + ['F' => 0]),
            ])
            ->addRow([
                'Average Salary Rate',
                $this->aggregate('People', $expectsPeople(CostlockerEnum::SALARY_TYPE), 'AVERAGE', 'F'),
            ])
            ->addRow([
                'Average Hourly Rate',
                $this->aggregate('People', $expectsPeople(CostlockerEnum::HOURLY_RATE_TYPE), 'AVERAGE', 'F'),
            ])
            ->addRow(['Timesheet...', ''], 'cccccc')
            ->addRow([
                'Billable ratio = %0',
                $this->aggregate('People', $expectsPeople() + ['H' => $this->escape('=0')]),
            ])
            ->addRow([
                'Billable ratio < %50',
                $this->aggregate(
                    'People',
                    $expectsPeople() + ['I' => [$this->escape('>0'), $this->escape('<=50')]]
                ),
            ])
            ->addRow([
                'Billable ratio > %75',
                $this->aggregate('People', $expectsPeople() + ['I' => $this->escape('>=75')]),
            ])
            ->addRow(['Created before...', ''], 'cccccc')
            ->addRow([
                $yearsCells[1],
                $this->aggregate('People', $expectsPeople() + ['D' => "{$this->escape('<')}&A{$xls->getRowId()}"]),
            ])
            ->addRow([
                $yearsCells[2],
                $this->aggregate('People', $expectsPeople() + ['D' => "{$this->escape('<')}&A{$xls->getRowId()}"]),
            ])
            ->addRow([
                $yearsCells[3],
                $this->aggregate('People', $expectsPeople() + ['D' => "{$this->escape('<')}&A{$xls->getRowId()}"]),
            ])
            ->skipRows(1);
    }

    private function analyzeClients(XlsBuilder $xls, array $yearsCells)
    {
        $activeClients = ['B' => $this->escape('YES')];
        $xls
            ->addRow(['Clients', ''], 'b6ef8f')
            ->addRow([
                'Active clients',
                $this->aggregate('Clients', $activeClients),
            ])
            ->addRow([
                'Lost clients',
                $this->aggregate('Clients', ['I' => $this->escape('>0'), 'J' => $this->escape('=0')]),
            ])
            ->addRow(['Created before...', ''], 'cccccc')
            ->addRow([
                $yearsCells[1],
                $this->aggregate('Clients', $activeClients + ['C' => "{$this->escape('<')}&A{$xls->getRowId()}"]),
            ])
            ->addRow([
                $yearsCells[2],
                $this->aggregate('Clients', $activeClients + ['C' => "{$this->escape('<')}&A{$xls->getRowId()}"]),
            ])
            ->addRow([
                $yearsCells[3],
                $this->aggregate('Clients', $activeClients + ['C' => "{$this->escape('<')}&A{$xls->getRowId()}"]),
            ])
            ->skipRows(1);
    }

    private function analyzeProjects(XlsBuilder $xls, array $yearsCells)
    {
        $dateCondition = ['D' => "{$this->escape('>')}&B{$xls->getRowId(3)}"];

        $expectsProjects = function ($state) {
            return ['C' => $this->escape($state)];
        };
        $xls
            ->addRow(['Projects', ''], 'b6ef8f')
            ->addRow([
                'Running projects',
                $this->aggregate('Projects', $expectsProjects(CostlockerEnum::RUNNING_PROJECT)),
            ])
            ->addRow([
                'Finished projects',
                $this->aggregate('Projects', $expectsProjects(CostlockerEnum::FINISHED_PROJECT)),
            ])
            ->addRow(['Created after', $yearsCells[3]->defaultStyle('cccccc')], 'cccccc')
            ->addRow([
                'All projects',
                $this->aggregate('Projects', $dateCondition),
            ])
            ->addRow([
                'Running projects',
                $this->aggregate('Projects', $expectsProjects(CostlockerEnum::RUNNING_PROJECT) + $dateCondition),
            ])
            ->addRow([
                'Finished projects',
                $this->aggregate(
                    'Projects',
                    $expectsProjects(CostlockerEnum::FINISHED_PROJECT) + $dateCondition
                ),
            ])
            ->skipRows(1);
    }

    private function analyzeProjectExpenses(XlsBuilder $xls)
    {
        $dateStart = (new \DateTime('first day of previous month'))->modify('- 10 months');
        $dateEnd = (new \DateTime('last day of this month'));
        $cellStart = "B{$xls->getRowId(1)}";
        $cellEnd = "B{$xls->getRowId(2)}";
        $xls
            ->addRow(['Projects Expenses', ''], 'b6ef8f')
            ->addRow(
                [
                    'Compared: Purchased date start',
                    $this->dateToCell($dateStart, NumberFormat::FORMAT_DATE_YYYYMMDD2)->defaultStyle('cccccc'),
                ],
                'cccccc'
            )
            ->addRow(
                [
                    'Compared: Purchased date end',
                    $this->dateToCell($dateEnd, NumberFormat::FORMAT_DATE_YYYYMMDD2)->defaultStyle('cccccc'),
                ],
                'cccccc'
            )
            ->addRow([
                'Purchased amount',
                $this->aggregate(
                    'Expenses',
                    [
                        'E' => ["{$this->escape('>=')}&{$cellStart}", "{$this->escape('<=')}&{$cellEnd}"],
                    ],
                    'SUM',
                    'F'
                ),
            ])
            ->addRow([
                'Billed amount',
                $this->aggregate(
                    'Expenses',
                    [
                        'E' => ["{$this->escape('>=')}&{$cellStart}", "{$this->escape('<=')}&{$cellEnd}"],
                    ],
                    'SUM',
                    'G'
                ),
            ])
            ->addRow([
                'Profit',
                ["=B{$xls->getRowId(-1)}-B{$xls->getRowId(-2)}", NumberFormat::FORMAT_NUMBER_00],
            ])
            ->addRow([
                'Profit margin',
                [
                    "=IF(B{$xls->getRowId(-2)} <> 0, B{$xls->getRowId(-1)}/B{$xls->getRowId(-2)}, {$this->escape('')})",
                    NumberFormat::FORMAT_PERCENTAGE_00
                ],
            ])
            ->skipRows(1);
    }

    private function analyzeProjectBilling(XlsBuilder $xls)
    {
        $dateStart = (new \DateTime('first day of previous month'))->modify('- 10 months');
        $dateEnd = (new \DateTime('last day of this month'));
        $cellStart = "B{$xls->getRowId(1)}";
        $cellEnd = "B{$xls->getRowId(2)}";
        $xls
            ->addRow(['Projects Billing', ''], 'b6ef8f')
            ->addRow(
                [
                    'Compared: Billed date start',
                    $this->dateToCell($dateStart, NumberFormat::FORMAT_DATE_YYYYMMDD2)->defaultStyle('cccccc'),
                ],
                'cccccc'
            )
            ->addRow(
                [
                    'Compared: Billed date end',
                    $this->dateToCell($dateEnd, NumberFormat::FORMAT_DATE_YYYYMMDD2)->defaultStyle('cccccc'),
                ],
                'cccccc'
            )
            ->addRow([
                'Billed planned amount',
                $this->aggregate(
                    'Billing',
                    [
                        'D' => $this->escape(CostlockerEnum::BILLING_DRAFT),
                        'C' => ["{$this->escape('>=')}&{$cellStart}", "{$this->escape('<=')}&{$cellEnd}"],
                    ],
                    'SUM',
                    'E'
                ),
            ])
            ->addRow([
                'Billed amount',
                $this->aggregate(
                    'Billing',
                    [
                        'D' => $this->escape(CostlockerEnum::BILLING_SENT),
                        'C' => ["{$this->escape('>=')}&{$cellStart}", "{$this->escape('<=')}&{$cellEnd}"],
                    ],
                    'SUM',
                    'E'
                ),
            ])
            ->skipRows(1);
    }

    private function analyzeTimesheet(XlsBuilder $xls, array $dates, ReportSettings $settings)
    {
        $xls
            ->addRow(['Timesheet', ''], 'b6ef8f')
            ->addRow(
                [
                    'Min date',
                    $this->dateToCell($dates['start'], NumberFormat::FORMAT_DATE_YYYYMMDD2)->defaultStyle('cccccc'),
                ],
                'cccccc'
            )
            ->addRow(
                [
                    'Max date',
                    $this->dateToCell($dates['end'], NumberFormat::FORMAT_DATE_YYYYMMDD2)->defaultStyle('cccccc'),
                ],
                'cccccc'
            )
            ->addRow([
                'Tracked hours',
                $this->aggregate('Timesheet', [], 'SUM', 'F'),
            ])
            ->addRow([
                'Billable hours',
                $this->aggregate('Timesheet', [], 'SUM', 'G'),
                ["=B{$xls->getRowId()}/B{$xls->getRowId(-1)}", NumberFormat::FORMAT_PERCENTAGE_00],
            ])
            ->addRow(
                [
                    "Internal client",
                    $settings->customConfig['internalClientName']
                ],
                'cccccc'
            )
            ->addRow([
                'Tracked hours',
                $this->aggregate('Timesheet', ['B' => "B{$xls->getRowId(-1)}"], 'SUM', 'F'),
                ["=B{$xls->getRowId()}/B{$xls->getRowId(-3)}", NumberFormat::FORMAT_PERCENTAGE_00],
            ])
            ->addRow([
                'Billable hours',
                $this->aggregate('Timesheet', ['B' => "B{$xls->getRowId(-1)}"], 'SUM', 'G'),
            ])
            ->skipRows(1);
    }

    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    private function addSourceData(array $report, array $yearsCells)
    {
        $this->entitiesToWorksheet(
            'Projects',
            [
                'Project',
                'Client',
                'State',
                'Date start',
                'Date end',
                'Budget type',
                'Tags'
            ],
            $report['projects'],
            function (array $project) use ($report) {
                return [
                    [$project['name'], NumberFormat::FORMAT_GENERAL, [], $project['url']],
                    $report['clients'][$project['client_id']]['name'],
                    $project['state'],
                    $this->dateToCell($project['dates']['start']),
                    $this->dateToCell($project['dates']['end']),
                    $project['budget_type'],
                    implode(', ', $project['tags']),
                ];
            }
        );
        $this->entitiesToWorksheet(
            'Expenses',
            [
                'Project',
                'Client',
                'Project Date end',
                'Purchased date',
                'Date',
                ['Purchased amount', 'SUM'],
                ['Billed amount', 'SUM'],
                'Name',
            ],
            $report['expenses'],
            function (array $expense, $rowId) use ($report) {
                $project = $report['projects'][$expense['project_id']];
                return [
                    [$project['name'], NumberFormat::FORMAT_GENERAL, [], $expense['url']],
                    $report['clients'][$project['client_id']]['name'],
                    $this->dateToCell($project['dates']['end']),
                    $this->dateToCell($expense['dates']['purchased']),
                    ["=IF(ISBLANK(C{$rowId}),B{$rowId},C{$rowId})", 'MMMM YYYY'],
                    $expense['amounts']['purchased'],
                    $expense['amounts']['billed'],
                    $expense['name'],
                ];
            }
        );
        $this->entitiesToWorksheet(
            'Billing',
            [
                'Project',
                'Client',
                'Date',
                'Status',
                ['Billed amount', 'SUM'],
                'Name',
            ],
            $report['billing'],
            function (array $billing) use ($report) {
                $project = $report['projects'][$billing['project_id']];
                return [
                    [$project['name'], NumberFormat::FORMAT_GENERAL, [], $billing['url']],
                    $report['clients'][$project['client_id']]['name'],
                    $this->dateToCell($billing['dates']['billed']),
                    $billing['amounts']['status'],
                    $billing['amounts']['billed'],
                    $billing['name'],
                ];
            }
        );
        $this->entitiesToWorksheet(
            'Timesheet',
            [
                'Project',
                'Client',
                'Person ID',
                'Person',
                'Month',
                ['Tracked hours', 'SUM'],
                ['Billable hours', 'SUM'],
            ],
            $report['timesheet']['data'],
            function (array $entry) use ($report) {
                return [
                    $entry['project_id'] ? $report['projects'][$entry['project_id']]['name'] : '[No project]',
                    $entry['client_id'] ? $report['clients'][$entry['client_id']]['name'] : '[No client]',
                    $entry['person_id'],
                    $report['people'][$entry['person_id']]['name'],
                    $this->dateToCell($entry['dates']['month']),
                    ["={$entry['seconds']['tracked']}/3600.0", NumberFormat::FORMAT_NUMBER_00],
                    ["={$entry['seconds']['billable']}/3600.0", NumberFormat::FORMAT_NUMBER_00],
                ];
            }
        );
        if ($report['projectPeople']) {
            $this->entitiesToWorksheet(
                'Project People',
                [
                    'Project',
                    'Activity',
                    'Person ID',
                    'Person',
                    'Tasks',
                    'Hours estimated',
                    'Hours tracked',
                    'Hours billable',
                    'Hourly rate client',
                    'Hourly rate person',
                    'Hourly rate overhead',
                ],
                $report['projectPeople'],
                function (array $entry) use ($report) {
                    $formatNumber = function ($number) {
                        return $number !== null ? ["={$number}", NumberFormat::FORMAT_NUMBER_00] : null;
                    };
                    return [
                        $report['projects'][$entry['project_id']]['name'],
                        $entry['activity'],
                        $entry['person_id'],
                        $report['people'][$entry['person_id']]['name'],
                        implode(', ', $entry['tasks']),
                        $formatNumber($entry['hours']['estimated']),
                        $formatNumber($entry['hours']['tracked']),
                        $formatNumber($entry['hours']['billable']),
                        $formatNumber($entry['rates']['client']),
                        $formatNumber($entry['rates']['person']),
                        $formatNumber($entry['rates']['overhead']),
                    ];
                }
            );
        }

        $this->entitiesToWorksheet(
            'Salaries',
            [
                'Person ID',
                'Person',
                'Date from',
                'Date to',
                'Salary type',
                'Hourly rate',
                'Monthly hours',
                'Monthly salary',
            ],
            $report['people'],
            function (array $person) {
                return array_map(
                    function (array $salary) use ($person) {
                        return [
                            $person['id'],
                            $person['name'],
                            $this->dateToCell($salary['da_start']),
                            $this->dateToCell($salary['da_end']),
                            $salary['type'],
                            [$salary['hourly_rate'], NumberFormat::FORMAT_NUMBER_00],
                            $salary['salary_amount'],
                            $salary['salary_hours'],
                        ];
                    },
                    $person['salary']
                );
            },
            true
        );
        $this->entitiesToWorksheet(
            'People',
            [
                'Person ID',
                'Person',
                'Is active?',
                'Created at',
                'Payment type',
                'Hourly rate',
                'Tracked hours',
                'Billable hours',
                'Billable ratio',
                'Last salary change (= deactivation for inactive)',
            ],
            $report['people'],
            function (array $person, $rowId) {
                return [
                    $person['id'],
                    [$person['name'], NumberFormat::FORMAT_GENERAL, [], $person['url']],
                    $this->boolToCell($person['is_active']),
                    $this->dateToCell($person['da_created']),
                    $person['current_salary']['type'],
                    [$person['current_salary']['hourly_rate'], NumberFormat::FORMAT_NUMBER_00],
                    $this->aggregate('Timesheet', ['C' => "A{$rowId}"], 'SUM', 'F'),
                    $this->aggregate('Timesheet', ['C' => "A{$rowId}"], 'SUM', 'G'),
                    ["=H{$rowId}/G{$rowId}", NumberFormat::FORMAT_PERCENTAGE_00],
                    $this->aggregate(
                        'Salaries',
                        ['A' => "A{$rowId}", 'D' => $this->escape('')],
                        'MAX',
                        'C',
                        'MMMM YYYY'
                    ),
                ];
            }
        );
        $this->entitiesToWorksheet(
            'Clients',
            [
                'Client',
                'Is active?',
                'Created at',
                'First Billing',
                'Last Billing',
                ['Billing count', 'SUM'],
                ['Billing sum', 'SUM'],
                'Billed after:',
                [$yearsCells[3]->highlight(), 'SUM'],
                [$yearsCells[1]->highlight(), 'SUM'],
                ["Billed after [%]", 'SUM'],
            ],
            $report['clients'],
            function (array $client, $rowId) {
                $dateFormat = NumberFormat::FORMAT_DATE_YYYYMMDD2;
                return [
                    [$client['name'], NumberFormat::FORMAT_GENERAL, [], $client['url']],
                    $this->boolToCell($client['is_active']),
                    $this->dateToCell($client['da_created']),
                    $this->aggregate('Billing', ['B' => "A{$rowId}"], 'MIN', 'C', $dateFormat),
                    $this->aggregate('Billing', ['B' => "A{$rowId}"], 'MAX', 'C', $dateFormat),
                    $this->aggregate('Billing', ['B' => "A{$rowId}"], 'COUNT'),
                    $this->aggregate('Billing', ['B' => "A{$rowId}"], 'SUM', 'E'),
                    '',
                    $this->aggregate('Billing', ['B' => "A{$rowId}", 'C' => "{$this->escape('>=')}&I1"], 'SUM', 'E'),
                    $this->aggregate('Billing', ['B' => "A{$rowId}", 'C' => "{$this->escape('>=')}&J1"], 'SUM', 'E'),
                    ["=J{$rowId}/{$this->worksheets['Clients']('J', 'SUM')}", NumberFormat::FORMAT_PERCENTAGE_00],
                ];
            }
        );
    }

    private function entitiesToWorksheet(
        $title,
        array $rawHeaders,
        array $entities,
        $buildRow,
        $areMultipleRowsBuilt = false
    ) {
        $xls = $this->createWorksheet($title);

        $headers = [];
        foreach ($rawHeaders as $index => $headerConfig) {
            $aggregation = 'COUNTA';
            $header = $headerConfig;
            if (is_array($headerConfig)) {
                list($header, $aggregation) = $headerConfig;
            }
            $headers[] = [
                'cell' => is_string($header) ? $this->headerCell($header, 'd6dce5') : $header,
                'total' => function () use ($aggregation, $title, $xls, $index) {
                    return "={$aggregation}({$this->worksheets[$title]($xls->indexToLetter($index))})";
                },
            ];
        }

        $xls
            ->addRow(
                array_map(
                    function ($header) {
                        return $header['cell'];
                    },
                    $headers
                )
            )
            ->autosizeColumnsInCurrentRow();

        $firstRow = $xls->getRowId();
        $this->worksheets[$title] = $this->prepareAggregation($xls, $firstRow, count($entities) + 1);

        $rowCount = 0;
        foreach ($entities as $entity) {
            $rowOrRows = $buildRow($entity, $xls->getRowId());
            $rows = $areMultipleRowsBuilt ? $rowOrRows : [$rowOrRows];
            foreach ($rows as $row) {
                $xls->addRow($row);
                $rowCount++;
            }
        }

        if ($areMultipleRowsBuilt) {
            $this->worksheets[$title] = $this->prepareAggregation($xls, $firstRow, $rowCount + 1);
        }

        $xls->addRow(
            array_map(
                function ($header) {
                    return $header['total']();
                },
                $headers
            ),
            'ffff00'
        );
    }

    private function prepareAggregation(XlsBuilder $xls, $firstRow, $lastRow)
    {
        return function ($column, $type = 'range') use ($xls, $firstRow, $lastRow) {
            if ($type == 'SUM') {
                $sumRow = $lastRow + 1;
                return "\${$column}\${$sumRow}";
            }
            return "{$xls->getWorksheetReference()}{$column}{$firstRow}:{$column}{$lastRow}";
        };
    }

    private function dateToCell($datetimeOrYmdDate, $xlsFormat = 'MMMM YYYY')
    {
        if (!$datetimeOrYmdDate) {
            return null;
        }
        $date = $datetimeOrYmdDate instanceof \DateTime
            ? $datetimeOrYmdDate : \DateTime::createFromFormat('Y-m-d', $datetimeOrYmdDate);
        return $this
            ->cell("=DATE({$date->format('Y, m, d')})")
            ->format($xlsFormat);
    }

    private function boolToCell($isTrue)
    {
        $xlsBool = $isTrue ? 'true' : 'false';
        return "=IF({$xlsBool}, {$this->escape('YES')}, {$this->escape('NO')})";
    }

    private function aggregate($title, array $conditions, $function = 'COUNT', $rangeColumn = null, $format = null)
    {
        $args = [];
        if ($rangeColumn) {
            $args[] = $this->worksheets[$title]($rangeColumn);
        }
        foreach ($conditions as $column => $expectedValue) {
            $expectedValues = (array) $expectedValue;
            foreach ($expectedValues as $value) {
                $args[] = $this->worksheets[$title]($column);
                $args[] = $value;
            }
        }
        $formula = "={$function}";
        if ($conditions) {
            $formula .= 'IFS';
        }
        $formula .= '(' . implode(', ', $args) . ')';
        return [
            $formula,
            $format ?: ($function == 'COUNT' ? NumberFormat::FORMAT_NUMBER : NumberFormat::FORMAT_NUMBER_00),
        ];
    }

    private function escape($string)
    {
        return "\"{$string}\"";
    }
}
