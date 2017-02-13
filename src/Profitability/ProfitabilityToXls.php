<?php

namespace Costlocker\Reports\Profitability;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Costlocker\Reports\ReportSettings;
use Costlocker\Reports\XlsBuilder;

class ProfitabilityToXls
{
    const MODE_SUMMARY = 'summary';

    private $spreadsheet;
    private $months;

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
    }

    public function __invoke(ProfitabilityReport $report, ReportSettings $settings)
    {
        $currencyFormat = XlsBuilder::getCurrencyFormat($settings->currency);

        $aggregatedPositions = array_fill_keys($settings->getAvailablePositions(), []);
        if ($settings->personsSettings && !$this->months) {
            $this->months = new XlsBuilder($this->spreadsheet, 'Months');
            $this->months
                ->addRow(['Počet prodaných hodin', ['=6000', NumberFormat::FORMAT_NUMBER]])
                ->addRow(['Celkový plán nákladů', ['=5000000', $currencyFormat]])
                ->addRow(['Průměrná fakturovaná cena', ['=1300', $currencyFormat]])
                ->addRow(['Plánovaná vytíženost', ['=0.65', NumberFormat::FORMAT_PERCENTAGE_00]])
                ->skipRows(2);
        }

        $monthReport = new XlsBuilder($this->spreadsheet, $report->selectedMonth->format('Y-m'));
        $monthReport->addHeaders([
            ['IS PROFITABLE?', 'd6dce5'],
            ['NAME', 'd6dce5'],
            ['POSITION', 'd6dce5'],
            ['PROJECT', 'd6dce5'],
            ['CLIENT', 'd6dce5'],
            ['Contracted', 'd0cece', 'HRS'],
            ['Wages', 'd0cece', $settings->currency],
            ['Tracked', 'd0cece', 'HRS'],
            ['Estimate', 'd0cece', 'HRS'],
            ['BILLABLE', 'ffd966', 'hrs'],
            ['NON-BILLABLE', 'd0cece', 'hrs'],
            ['CLIENT RATE', 'ffd966', $settings->currency],
            ['INVOICED PRICE', 'ffd966', $settings->currency],
            ['SALES', 'fbe5d6', '%'],
            ['NO SALES', 'fbe5d6', '%'],
            ['PROFITABILITY', 'fbe5d6', $settings->currency],
            ["NON-BILLABLE On internal projects", 'f4b183', $settings->currency],
            ["NON-BILLABLE On billable projects", 'f4b183', $settings->currency],
            ["TOTAL Non-Billable", 'ed7d31', $settings->currency],
            ["TOTAL Billable", 'ffd966', $settings->currency],
        ]);

        foreach ($report->getActivePeople() as $person) {
            $summaryRow = $monthReport->getRowId();
            $firstProjectRow = $monthReport->getRowId(1);
            $lastProjectRow = $monthReport->getRowId(count($person['projects']));
            $position = $settings->getPosition($person['name']);
            $aggregatedPositions[$position][] = $summaryRow;

            $monthReport
                ->addRow(
                    [
                        "=IF(P{$summaryRow}>0, \"YES\", \"NO\")",
                        $person['name'],
                        $position,
                        '',
                        '',
                        [
                            $person['is_employee'] ?
                                ($settings->getHoursSalary($person['name'], "=H{$summaryRow}") ?: $person['salary_hours']) :
                                "=H{$summaryRow}",
                            NumberFormat::FORMAT_NUMBER_00
                        ],
                        [
                            $person['is_employee'] ? $person['salary_amount'] : "=F{$summaryRow}*{$person['hourly_rate']}",
                            $currencyFormat
                        ],
                        ["=SUM(H{$firstProjectRow}:H{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                        ["=SUM(I{$firstProjectRow}:I{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                        ["=SUM(J{$firstProjectRow}:J{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                        ["=SUM(K{$firstProjectRow}:K{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                        '',
                        ["=SUM(M{$firstProjectRow}:M{$lastProjectRow})", $currencyFormat],
                        ["=SUM(N{$firstProjectRow}:N{$lastProjectRow})", NumberFormat::FORMAT_PERCENTAGE_00],
                        ["=1-N{$summaryRow}", NumberFormat::FORMAT_PERCENTAGE_00],
                        ["=M{$summaryRow}-G{$summaryRow}", $currencyFormat],
                        ["=SUM(Q{$firstProjectRow}:Q{$lastProjectRow})", $currencyFormat],
                        ["=SUM(R{$firstProjectRow}:R{$lastProjectRow})", $currencyFormat],
                        ["=Q{$summaryRow}+R{$summaryRow}", $currencyFormat],
                        ["=(G{$summaryRow}/F{$summaryRow})*J{$summaryRow}", $currencyFormat],
                    ],
                    'bdd7ee'
                );

            foreach ($person['projects'] as $project) {
                $projectRowId = $monthReport->getRowId();
                $isBillableProject = $project['client_rate'] > 0;
                $nonBillableMoney = ["=-1*((G{$summaryRow}/F{$summaryRow})*K{$projectRowId})", $currencyFormat];
                $remainingBillable =
                    "I{$projectRowId}-({$project['hrs_tracked_total']}-{$project['hrs_tracked_after_month']}-H{$projectRowId})";

                $monthReport
                    ->setRowVisibility($settings->exportSettings != self::MODE_SUMMARY)
                    ->addRow([
                        '',
                        $person['name'],
                        $settings->getPosition($person['name']),
                        $project['name'],
                        $project['client'],
                        '',
                        '',
                        [$project['hrs_tracked_month'], NumberFormat::FORMAT_NUMBER_00],
                        [$project['hrs_budget'], NumberFormat::FORMAT_NUMBER_00],
                        [
                            "=MIN(H{$projectRowId}, MAX(0, {$remainingBillable}))",
                            NumberFormat::FORMAT_NUMBER_00
                        ],
                        ["=MAX(0, H{$projectRowId}-J{$projectRowId})", NumberFormat::FORMAT_NUMBER_00],
                        [$project['client_rate'], $currencyFormat],
                        ["=J{$projectRowId}*L{$projectRowId}", $currencyFormat],
                        ["=J{$projectRowId}/F{$summaryRow}", NumberFormat::FORMAT_PERCENTAGE_00],
                        '',
                        '',
                        $isBillableProject ? '' : $nonBillableMoney,
                        $isBillableProject ? $nonBillableMoney : '',
                        '',
                        '',
                    ]);
            }
        }

        $monthReport
            ->removeColumnIf('C', !$settings->personsSettings)
            ->hideColumn('T');

        if ($settings->personsSettings) {
            $this->months
                ->addSuperHeader(
                    [$report->selectedMonth->format('Y-m'), '92d050'],
                    'Q'
                )
                ->addHeaders([
                    ['Position', 'adb9ca'],
                    ['Employees', 'adb9ca'],
                    ['Billable', 'ffc000', '%'],
                    ['Billable', 'ffc000', $settings->currency],
                    ['Total Non-Billable', '8faadc', '%'],
                    ['Total Non-Billable', '8faadc', $settings->currency],
                    ["NON-BILLABLE On internal projects", '8faadc', $settings->currency],
                    ["NON-BILLABLE On billable projects", '8faadc', $settings->currency],
                    ['Profitability', 'ffff66', $settings->currency],
                    ['Wages', '66ffff', $settings->currency],
                    ['', 'ffffff'],
                    ['Plan', 'cc99ff', '%'],
                    ['Plan', 'cc99ff', $settings->currency],
                    ['Billable', 'ffc000', '%'],
                    ['Billable', 'ffc000', $settings->currency],
                    ['Diff', 'ff0000', '%'],
                    ['Diff', 'ff0000', $settings->currency],
                ]);

            $firstPosition = $this->months->getRowId();
            $summaryRow = $this->months->getRowId(count($aggregatedPositions));
            foreach ($aggregatedPositions as $position => $rows) {
                $positionRowId = $this->months->getRowId();
                $aggregate = function ($function, $column) use ($rows, $monthReport) {
                    if (!$rows) {
                        return 0;
                    }

                    $references = array_map(
                        function ($rowId) use ($monthReport, $column) {
                            return $monthReport->getCellReference($column, $rowId);
                        },
                        $rows
                    );

                    return "={$function}(" . implode(',', $references) . ')';
                };
                $this->months
                    ->setRowVisibility(count($rows) > 0)
                    ->addRow([
                        $position,
                        $aggregate('COUNTA', 'B'),
                        [$aggregate('AVERAGE', 'N'), NumberFormat::FORMAT_PERCENTAGE_00],
                        [$aggregate('SUM', 'T'), $currencyFormat],
                        [$aggregate('AVERAGE', 'O'), NumberFormat::FORMAT_PERCENTAGE_00],
                        [$aggregate('SUM', 'S'), $currencyFormat],
                        [$aggregate('SUM', 'Q'), $currencyFormat],
                        [$aggregate('SUM', 'R'), $currencyFormat],
                        [$aggregate('SUM', 'P'), $currencyFormat],
                        [$aggregate('SUM', 'G'), $currencyFormat],
                        '',
                        ['=B4', NumberFormat::FORMAT_PERCENTAGE_00],
                        ["=(B1*L{$positionRowId})*B3/B{$summaryRow}*B{$positionRowId}", $currencyFormat],
                        ["=C{$positionRowId}", NumberFormat::FORMAT_PERCENTAGE_00],
                        ["=D{$positionRowId}", $currencyFormat],
                        ["=N{$positionRowId}-L{$positionRowId}", NumberFormat::FORMAT_PERCENTAGE_00],
                        ["=C{$positionRowId}-M{$positionRowId}", $currencyFormat],
                    ]);
            }

            $aggregateAll = function ($column) use ($firstPosition, $summaryRow) {
                return "=SUM({$column}{$firstPosition}:{$column}" . ($summaryRow - 1) . ')';
            };
            $this->months
                ->addRow([
                    '',
                    $aggregateAll('B'),
                    '',
                    [$aggregateAll('D'), $currencyFormat],
                    '',
                    [$aggregateAll('F'), $currencyFormat],
                    [$aggregateAll('G'), $currencyFormat],
                    [$aggregateAll('H'), $currencyFormat],
                    [$aggregateAll('I'), $currencyFormat],
                    [$aggregateAll('J'), $currencyFormat],
                    '',
                    '',
                    [$aggregateAll('M'), $currencyFormat],
                    '',
                    [$aggregateAll('O'), $currencyFormat],
                    '',
                    [$aggregateAll('Q'), $currencyFormat],
                ])
                ->skipRows(1)
                ->addRow(
                    [
                        11 => "Zisk dle plánu",
                        "=M{$summaryRow}-B2",
                        '',
                        'Ztráta',
                        "=M{$summaryRow}-O{$summaryRow}"
                    ],
                    'ffccff'
                )
                ->skipRows(3);
        }
    }
}
