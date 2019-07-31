<?php

namespace Costlocker\Reports\Custom\Company;

use Costlocker\Reports\Transform\TransformToXls;
use Costlocker\Reports\ReportSettings;
use Costlocker\Reports\Transform\XlsBuilder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
class CompanyOverviewToXls extends TransformToXls
{
    private $dateIntervals;
    private $worksheets = [
        'topClients' => [],
        'clientMonths' => [],
        'hoursTracked' => null,
        'hoursBillable' => null,
        'billingTotal' => null,
        'billingPercentage' => null,
        'billingHistory' => null,
    ];

    public function __invoke(array $report, ReportSettings $settings)
    {
        $xls = $this->createWorksheet('Overview');
        $this->loadDateIntervals($xls, $report['dateIntervals']);
        $this->addSourceData($report, $settings);
        $this->analyze($xls);
    }

    private function loadDateIntervals(XlsBuilder $xls, array $dateIntervals)
    {
        $xls
            ->addRow(['Date range'], 'b6ef8f')
            ->addRow(['Min'], 'eeeeee')
            ->addRow(['Max'], 'eeeeee');
        foreach ($dateIntervals as $index => $interval) {
            $column = $xls->indexToLetter($index + 1);
            $xls
                ->setCell($column, $xls->getRowId(-3), $this->headerCell($interval['title'], 'b6ef8f'))
                ->setCell($column, $xls->getRowId(-2), $this->dateToCell($interval['min']->format('Y-m-d')))
                ->setCell($column, $xls->getRowId(-1), $this->dateToCell($interval['max']->format('Y-m-d')));
            $cells = [
                'title' => "{$xls->getWorksheetReference()}{$column}{$xls->getRowId(-3)}",
                'min' => "{$xls->getWorksheetReference()}{$column}{$xls->getRowId(-2)}",
                'max' => "{$xls->getWorksheetReference()}{$column}{$xls->getRowId(-1)}",
            ];
            $this->dateIntervals[] = $cells + [
                'filter' => ["{$this->escape('>=')}&{$cells['min']}", "{$this->escape('<=')}&{$cells['max']}"],
                'getColumn' => function ($previousColumnsCount) use ($xls, $index) {
                    return $xls->indexToLetter($previousColumnsCount + $index);
                },
                'saveLinkedCell' => function ($type, $id = null) use ($xls) {
                    if (is_array($this->worksheets[$type])) {
                        $this->worksheets[$type][$id] = $xls->getRowId();
                    } else {
                        $this->worksheets[$type] = $xls->getRowId();
                    }
                },
                'getLinkedCell' => function ($type, $id = null) use ($column) {
                    $value = is_array($this->worksheets[$type])
                        ? $this->worksheets[$type][$id] : $this->worksheets[$type];
                    return "{$column}{$value}";
                },
            ];
        }
        $xls
            ->autosizeColumnsInCurrentRow()
            ->skipRows(1);
    }

    private function analyze(XlsBuilder $xls)
    {
        $helperRow = function ($formula) {
            return [
                'isVisible' => false,
                'formula' => $formula,
            ];
        };
        $columnsCount = count($this->dateIntervals);
        $xls
            ->addRow(['Metrics'], 'b6ef8f')
            ->mergeCells("A{$xls->getRowId(-1)}:{$xls->indexToLetter($columnsCount)}{$xls->getRowId(-1)}");
        foreach ($this->getMetrics($helperRow) as $title => $formula) {
            $definition = is_array($formula) ? $formula : [
                'isVisible' => true,
                'formula' => $formula,
            ];
            $row = [
                $this->cell($title, $definition['isVisible'] ? 'eeeeee' : 'ffffff')
            ];
            foreach ($this->dateIntervals as $interval) {
                $row[] = $definition['formula']($interval);
            }
            $xls
                ->setRowVisibility($definition['isVisible'])
                ->addRow($row);
        }
    }

    private function getMetrics($helperRow)
    {
        $clientBilling = function ($rank) use ($helperRow) {
            return $helperRow(function (array $interval) use ($rank) {
                $interval['saveLinkedCell']('topClients', $rank);
                return $this->aggregate('Clients', [$interval['getColumn'](8) => $rank], 'LARGE');
            });
        };
        $percentage = function ($a, $b) {
            return ["=IF({$b} <> 0, {$a}/{$b}, \"\")", NumberFormat::FORMAT_PERCENTAGE_00];
        };
        $clientPercentage = function ($rank) use ($percentage) {
            return function (array $interval) use ($rank, $percentage) {
                return $percentage(
                    $interval['getLinkedCell']('topClients', $rank),
                    $interval['getLinkedCell']('billingTotal')
                );
            };
        };
        $clientColumn = function ($rank, $extractedColumn, $format) use ($helperRow) {
            return $helperRow(function (array $interval) use ($rank, $extractedColumn, $format) {
                if ($extractedColumn == 'D') {
                    $interval['saveLinkedCell']('clientMonths', $rank);
                }
                $range = $this->worksheets['Clients']($interval['getColumn'](8), 'MATCH');
                $billedAmount = $interval['getLinkedCell']('topClients', $rank);
                $rowId = "MATCH({$billedAmount}, {$range}, 0)";
                return ["=INDIRECT(\"Clients!{$extractedColumn}\"&{$rowId})", $format];
            });
        };
        $clientMonths = function ($rank) {
            return function (array $interval) use ($rank) {
                $clientMonth = $interval['getLinkedCell']('clientMonths', $rank);
                return [
                    "=DATEDIF({$clientMonth}, {$interval['max']}, {$this->escape('M')})",
                    NumberFormat::FORMAT_NUMBER
                ];
            };
        };
        return [
            'Billing' => function (array $interval) {
                $interval['saveLinkedCell']('billingTotal');
                return $this->aggregate(
                    'Billing',
                    ['D' => $this->escape(CostlockerEnum::BILLING_SENT), 'C' => $interval['filter']],
                    'SUM',
                    'E'
                );
            },
            'Billing 10%' => $helperRow(function (array $interval) {
                $interval['saveLinkedCell']('billingPercentage');
                return ["={$interval['getLinkedCell']('billingTotal')}/10", NumberFormat::FORMAT_NUMBER_00];
            }),
            'Billing history' => $helperRow(function (array $interval) {
                $interval['saveLinkedCell']('billingHistory');
                return [
                    "=DATE(YEAR({$interval['max']})-2, MONTH({$interval['max']}), DAY({$interval['max']}))",
                    NumberFormat::FORMAT_DATE_YYYYMMDD2
                ];
            }),
            'Expenses - purchased' => function (array $interval) {
                return $this->aggregate('Expenses', ['E' => $interval['filter']], 'SUM', 'F');
            },
            'Expenses - billed' => function (array $interval) {
                return $this->aggregate('Expenses', ['E' => $interval['filter']], 'SUM', 'G');
            },
            'Timesheet - tracked hours' => function (array $interval) {
                $interval['saveLinkedCell']('hoursTracked');
                return $this->aggregate('Timesheet', ['E' => $interval['filter']], 'SUM', 'F');
            },
            'Timesheet - billable hours' => function (array $interval) {
                $interval['saveLinkedCell']('hoursBillable');
                return $this->aggregate('Timesheet', ['E' => $interval['filter']], 'SUM', 'G');
            },
            'Timesheet - billable ratio' => function (array $interval) use ($percentage) {
                return $percentage(
                    $interval['getLinkedCell']('hoursBillable'),
                    $interval['getLinkedCell']('hoursTracked')
                );
            },
            'People - has salary' => function (array $interval) {
                return $this->aggregate('People', [$interval['getColumn'](9) => $this->escape(">0")], 'COUNT');
            },
            'People - has salary > 2 years' => function (array $interval) {
                $historicalDate = $interval['getLinkedCell']('billingHistory');
                $monthsCount = "DATEDIF({$historicalDate}, {$interval['max']}, {$this->escape('M')})";
                return $this->aggregate(
                    'People',
                    [$interval['getColumn'](9) => "{$this->escape(">=")}&{$monthsCount}"],
                    'COUNT'
                );
            },
            'People - has tracked hours' => function (array $interval) {
                return $this->aggregate('People', [$interval['getColumn'](13) => $this->escape(">0")], 'COUNT');
            },
            'People - has billable hours' => function (array $interval) {
                return $this->aggregate('People', [$interval['getColumn'](17) => $this->escape(">0")], 'COUNT');
            },
            'Clients > 10% total' => function (array $interval) {
                return $this->aggregate(
                    'Clients',
                    [
                        $interval['getColumn'](8)
                            => "{$this->escape('>=')}&{$interval['getLinkedCell']('billingPercentage')}"
                    ],
                    'COUNT'
                );
            },
            'Clients > 10% total AND first billing > 2 years' => function (array $interval) {
                return $this->aggregate(
                    'Clients',
                    [
                        $interval['getColumn'](8)
                            => "{$this->escape('>=')}&{$interval['getLinkedCell']('billingPercentage')}",
                        'D'
                            => "{$this->escape('<=')}&{$interval['getLinkedCell']('billingHistory')}",
                    ],
                    'COUNT'
                );
            },
            'Billing - 1st client' => $clientBilling(1),
            'Billing - 2nd client' => $clientBilling(2),
            'Billing - 3rd client' => $clientBilling(3),
            'Billing - 1st client name' => $clientColumn(1, 'A', NumberFormat::FORMAT_GENERAL),
            'Billing - 2nd client name' => $clientColumn(2, 'A', NumberFormat::FORMAT_GENERAL),
            'Billing - 3rd client name' => $clientColumn(3, 'A', NumberFormat::FORMAT_GENERAL),
            'Billing % - 1st client' => $clientPercentage(1),
            'Billing % - 2nd client' => $clientPercentage(2),
            'Billing % - 3rd client' => $clientPercentage(3),
            'Billing first date - 1st client' => $clientColumn(1, 'D', NumberFormat::FORMAT_DATE_YYYYMMDD2),
            'Billing first date - 2nd client' => $clientColumn(2, 'D', NumberFormat::FORMAT_DATE_YYYYMMDD2),
            'Billing first date - 3rd client' => $clientColumn(3, 'D', NumberFormat::FORMAT_DATE_YYYYMMDD2),
            'Months since first billing - 1st client' => $clientMonths(1),
            'Months since first billing - 2nd client' => $clientMonths(2),
            'Months since first billing - 3rd client' => $clientMonths(3),
        ];
    }

    private function addSourceData(array $report, ReportSettings $settings)
    {
        if (in_array('Simple_Projects', $settings->customConfig['extraTakeout'] ?? [])) {
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
        }
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
            $report['timesheet'],
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
        $dateRanges = array_map(
            function (array $interval) {
                return "={$interval['title']}";
            },
            $this->dateIntervals
        );
        $this->entitiesToWorksheet(
            'People',
            array_merge(
                [
                    'Person ID',
                    'Person',
                    'Is active?',
                    'Created at',
                    'Payment type',
                    'Hourly rate',
                    'Salary from',
                    'Last salary change (= deactivation for inactive)',
                    'Months count',
                ],
                $dateRanges,
                ['Tracked hours'],
                $dateRanges,
                ['Billable hours'],
                $dateRanges
            ),
            $report['people'],
            function (array $person, $rowId) {
                return array_merge(
                    [
                        $person['id'],
                        [$person['name'], NumberFormat::FORMAT_GENERAL, [], $person['url']],
                        $this->boolToCell($person['is_active']),
                        $this->dateToCell($person['da_created']),
                        $person['current_salary']['type'],
                        [$person['current_salary']['hourly_rate'], NumberFormat::FORMAT_NUMBER_00],
                        $this->aggregate('Salaries', ['A' => "A{$rowId}"], 'MIN', 'C', 'MMMM YYYY'),
                        $this->aggregate(
                            'Salaries',
                            ['A' => "A{$rowId}", 'D' => $this->escape('')],
                            'MAX',
                            'C',
                            'MMMM YYYY'
                        ),
                        '',
                    ],
                    array_map(
                        function (array $interval) use ($rowId) {
                            $formula =
                                "=IFS("
                                // active person
                                . "C{$rowId}={$this->escape('YES')},"
                                . "IFERROR(DATEDIF(G{$rowId}, {$interval['max']}, {$this->escape('M')})+1, 0),"
                                // deactivated in the past
                                . "H{$rowId}<{$interval['min']},"
                                . "0,"
                                // deactivated in selected year
                                . "AND(H{$rowId}>={$interval['min']}, H{$rowId}<={$interval['max']}),"
                                . "DATEDIF(G{$rowId}, H{$rowId}, {$this->escape('M')})+1,"
                                // deactivated in the future
                                . "AND(H{$rowId}>{$interval['max']}),"
                                . "IFERROR(DATEDIF(G{$rowId}, {$interval['max']}, {$this->escape('M')})+1, 0)"
                                . ")";
                            return [$formula, NumberFormat::FORMAT_NUMBER];
                        },
                        $this->dateIntervals
                    ),
                    [''],
                    array_map(
                        function (array $interval) use ($rowId) {
                            return $this->aggregate(
                                'Timesheet',
                                ['C' => "A{$rowId}", 'E' => $interval['filter']],
                                'SUM',
                                'F'
                            );
                        },
                        $this->dateIntervals
                    ),
                    [''],
                    array_map(
                        function (array $interval) use ($rowId) {
                            return $this->aggregate(
                                'Timesheet',
                                ['C' => "A{$rowId}", 'E' => $interval['filter']],
                                'SUM',
                                'G'
                            );
                        },
                        $this->dateIntervals
                    )
                );
            }
        );
        $this->entitiesToWorksheet(
            'Clients',
            array_merge(
                [
                    'Client',
                    'Is active?',
                    'Created at',
                    'First Billing',
                    'Last Billing',
                    ['Billing count', 'SUM'],
                    ['Billing sum', 'SUM'],
                    'Date ranges',
                ],
                $dateRanges
            ),
            $report['clients'],
            function (array $client, $rowId) {
                $dateFormat = NumberFormat::FORMAT_DATE_YYYYMMDD2;
                return array_merge(
                    [
                        [$client['name'], NumberFormat::FORMAT_GENERAL, [], $client['url']],
                        $this->boolToCell($client['is_active']),
                        $this->dateToCell($client['da_created']),
                        $this->aggregate('Billing', ['B' => "A{$rowId}"], 'MIN', 'C', $dateFormat),
                        $this->aggregate('Billing', ['B' => "A{$rowId}"], 'MAX', 'C', $dateFormat),
                        $this->aggregate('Billing', ['B' => "A{$rowId}"], 'COUNT'),
                        $this->aggregate('Billing', ['B' => "A{$rowId}"], 'SUM', 'E'),
                        '',
                    ],
                    array_map(
                        function (array $interval) use ($rowId) {
                            return $this->aggregate(
                                'Billing',
                                ['B' => "A{$rowId}", 'C' => $interval['filter']],
                                'SUM',
                                'E'
                            );
                        },
                        $this->dateIntervals
                    )
                );
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
        $rowCount = 0;
        foreach ($entities as $entity) {
            $rowOrRows = $buildRow($entity, $xls->getRowId());
            $rows = $areMultipleRowsBuilt ? $rowOrRows : [$rowOrRows];
            foreach ($rows as $row) {
                $xls->addRow($row);
                $rowCount++;
            }
        }
        $this->worksheets[$title] = $this->prepareAggregation($xls, $firstRow, $rowCount + 1);

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
            $first = $type == 'MATCH' ? 1 : $firstRow;
            return "{$xls->getWorksheetReference()}{$column}{$first}:{$column}{$lastRow}";
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
        if ($conditions && $function != 'LARGE') {
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
