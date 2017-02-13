<?php

namespace Costlocker\Reports\Profitability;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Costlocker\Reports\ReportSettings;

class ProfitabilityToXls
{
    const MODE_SUMMARY = 'summary';

    private $spreadsheet;
    private $monthsAggregation;
    private $monthRowId;

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
    }

    public function __invoke(ProfitabilityReport $report, ReportSettings $settings)
    {
        $aggregatedPositions = array_fill_keys($settings->getAvailablePositions(), []);
        if ($settings->personsSettings && !$this->monthsAggregation) {
            $this->monthsAggregation = $this->spreadsheet->createSheet();
            $this->monthsAggregation->setTitle('Months');
            $this->monthRowId = 1;
            $this->monthsAggregation->setCellValue("A{$this->monthRowId}", 'Počet prodaných hodin');
            $this->monthsAggregation->setCellValue("B{$this->monthRowId}", '=6000');
            $this->monthRowId++;
            $this->monthsAggregation->setCellValue("A{$this->monthRowId}", 'Celkový plán nákladů');
            $this->monthsAggregation->setCellValue("B{$this->monthRowId}", '=5000000');
            $this->monthRowId++;
            $this->monthsAggregation->setCellValue("A{$this->monthRowId}", 'Průměrná fakturovaná cena');
            $this->monthsAggregation->setCellValue("B{$this->monthRowId}", '=1300');
            $this->monthRowId++;
            $this->monthsAggregation->setCellValue("A{$this->monthRowId}", 'Plánovaná vytíženost');
            $this->monthsAggregation->setCellValue("B{$this->monthRowId}", '=65');
            $this->monthRowId += 2;
        }

        $currencyFormat = $this->getCurrencyFormat($settings->currency);
        $headers = [
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
        ];

        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle($report->selectedMonth->format('Y-m'));

        $rowId = 1;
        $addStyle = function (&$rowId, $backgroundColor = null, $alignment = null) use ($worksheet) {
            $styles = [
                'borders' => [
                    'allborders' => [
                        'style' => Border::BORDER_THIN,
                        'color' => [
                            'rgb' => '000000'
                        ],
                    ],
                ],
            ];
            if ($backgroundColor) {
                $styles += [
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'type' => Fill::FILL_SOLID,
                        'startcolor' => array(
                            'rgb' => $backgroundColor != 'transparent' ? $backgroundColor : null,
                        ),
                    ],
                    'alignment' => [
                        'horizontal' => $alignment,
                    ],
                ];
            }
            $worksheet->getStyle("A{$rowId}:T{$rowId}")->applyFromArray($styles);
            $rowId++;
        };
        $setRowData = function ($rowId, array $rowData) use ($worksheet) {
            foreach ($rowData as $index => $value) {
                if (is_array($value)) {
                    list($value, $format) = $value;
                    $cell = $worksheet->setCellValue("{$this->indexToLetter($index)}{$rowId}", $value, true);
                    $cell->getStyle()->getNumberFormat()->setFormatCode($format);
                } else {
                    $worksheet->setCellValue("{$this->indexToLetter($index)}{$rowId}", $value);
                }
            }
        };

        $unitRowId = $rowId + 1;
        foreach ($headers as $index => $header) {
            $column = $this->indexToLetter($index);
            list($header, $backgroundColor, $unit) = $header + [2 => null];
            if ($unit) {
                $worksheet->setCellValue("{$column}{$rowId}", $header);
                $worksheet->setCellValue("{$column}{$unitRowId}", "[{$unit}]");
            } else {
                $worksheet->setCellValue("{$column}{$rowId}", $header);
                $worksheet->mergeCells("{$column}{$rowId}:{$column}{$unitRowId}");
            }
            $worksheet->getStyle("{$column}{$rowId}:{$column}{$unitRowId}")->applyFromArray([
                'fill' => [
                    'type' => Fill::FILL_SOLID,
                    'startcolor' => array(
                        'rgb' => $backgroundColor
                    ),
                ],
            ]);
            $worksheet->getColumnDimension($column)->setAutoSize(true);
        }
        $addStyle($rowId, 'transparent', Alignment::HORIZONTAL_CENTER);
        $addStyle($rowId, 'transparent', Alignment::HORIZONTAL_CENTER);

        foreach ($report->getActivePeople() as $person) {
            $summaryRow = $rowId;
            $firstProjectRow = $rowId + 1;
            $lastProjectRow = $rowId + count($person['projects']);
            $position = $settings->getPosition($person['name']);
            $aggregatedPositions[$position][] = $summaryRow;
            $rowData = [
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
            ];

            $setRowData($rowId, $rowData);
            $addStyle($rowId, 'bdd7ee');

            foreach ($person['projects'] as $project) {
                $isBillableProject = $project['client_rate'] > 0;
                $nonBillableMoney = ["=-1*((G{$summaryRow}/F{$summaryRow})*K{$rowId})", $currencyFormat];
                $remainingBillable =
                    "I{$rowId}-({$project['hrs_tracked_total']}-{$project['hrs_tracked_after_month']}-H{$rowId})";
                $rowData = [
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
                        "=MIN(H{$rowId}, MAX(0, {$remainingBillable}))",
                        NumberFormat::FORMAT_NUMBER_00
                    ],
                    ["=MAX(0, H{$rowId}-J{$rowId})", NumberFormat::FORMAT_NUMBER_00],
                    [$project['client_rate'], $currencyFormat],
                    ["=J{$rowId}*L{$rowId}", $currencyFormat],
                    ["=J{$rowId}/F{$summaryRow}", NumberFormat::FORMAT_PERCENTAGE_00],
                    '',
                    '',
                    $isBillableProject ? '' : $nonBillableMoney,
                    $isBillableProject ? $nonBillableMoney : '',
                ];
                $setRowData($rowId, $rowData);
                if ($settings->exportSettings == self::MODE_SUMMARY) {
                    $worksheet->getRowDimension($rowId)->setVisible(false);
                }
                $addStyle($rowId);
            }
        }

        if (!$settings->personsSettings) {
            $worksheet->removeColumn('C');
        }
        $worksheet->getColumnDimension('T')->setVisible(false);

        if ($settings->personsSettings) {
            $this->monthsAggregation->setCellValue("A{$this->monthRowId}", $report->selectedMonth->format('Y-m'));
            $this->monthsAggregation->mergeCells("A{$this->monthRowId}:J{$this->monthRowId}");
            $this->monthRowId++;
            $this->monthsAggregation->setCellValue("A{$this->monthRowId}", 'Position');
            $this->monthsAggregation->setCellValue("B{$this->monthRowId}", 'Employees');
            $this->monthsAggregation->setCellValue("C{$this->monthRowId}", 'Billable %');
            $this->monthsAggregation->setCellValue("D{$this->monthRowId}", 'Billable CZK');
            $this->monthsAggregation->setCellValue("E{$this->monthRowId}", 'Total Non-Billable %');
            $this->monthsAggregation->setCellValue("F{$this->monthRowId}", 'Total Non-Billable CZK');
            $this->monthsAggregation->setCellValue("G{$this->monthRowId}", 'Internal Non-Billable CZK');
            $this->monthsAggregation->setCellValue("H{$this->monthRowId}", 'Billable Non-Billable CZK');
            $this->monthsAggregation->setCellValue("I{$this->monthRowId}", 'Profitability');
            $this->monthsAggregation->setCellValue("J{$this->monthRowId}", 'Wages');
            $this->monthsAggregation->setCellValue("L{$this->monthRowId}", 'Plan %');
            $this->monthsAggregation->setCellValue("M{$this->monthRowId}", 'Plan CZK');
            $this->monthsAggregation->setCellValue("N{$this->monthRowId}", 'Billable %');
            $this->monthsAggregation->setCellValue("O{$this->monthRowId}", 'Billable CZK');
            $this->monthsAggregation->setCellValue("P{$this->monthRowId}", 'Diff %');
            $this->monthsAggregation->setCellValue("Q{$this->monthRowId}", 'Diff CZK');
            $this->monthRowId++;

            $firstPosition = $this->monthRowId;
            $summaryRow = $firstPosition + count($aggregatedPositions);
            foreach ($aggregatedPositions as $position => $rows) {
                $aggregate = function ($function, $column) use ($rows, $worksheet) {
                    if (!$rows) {
                        return 0;
                    }

                    $references = array_map(
                        function ($rowId) use ($worksheet, $column) {
                            return "'{$worksheet->getTitle()}'!{$column}{$rowId}";
                        },
                        $rows
                    );

                    return "={$function}(" . implode(',', $references) . ')';
                };
                $this->monthsAggregation->setCellValue("A{$this->monthRowId}", $position);
                $this->monthsAggregation->setCellValue("B{$this->monthRowId}", $aggregate('COUNTA', 'B'));
                $this->monthsAggregation->setCellValue("C{$this->monthRowId}", $aggregate('AVERAGE', 'N'));
                $this->monthsAggregation->setCellValue("D{$this->monthRowId}", $aggregate('SUM', 'T'));
                $this->monthsAggregation->setCellValue("E{$this->monthRowId}", $aggregate('AVERAGE', 'O'));
                $this->monthsAggregation->setCellValue("F{$this->monthRowId}", $aggregate('SUM', 'S'));
                $this->monthsAggregation->setCellValue("G{$this->monthRowId}", $aggregate('SUM', 'Q'));
                $this->monthsAggregation->setCellValue("H{$this->monthRowId}", $aggregate('SUM', 'R'));
                $this->monthsAggregation->setCellValue("I{$this->monthRowId}", $aggregate('SUM', 'P'));
                $this->monthsAggregation->setCellValue("J{$this->monthRowId}", $aggregate('SUM', 'G'));
                // forecast
                $this->monthsAggregation->setCellValue("L{$this->monthRowId}", '=B4');
                $this->monthsAggregation->setCellValue("M{$this->monthRowId}",
                    "=(B1*L{$this->monthRowId})*B3/B{$summaryRow}*B{$this->monthRowId}"
                );
                $this->monthsAggregation->setCellValue("N{$this->monthRowId}", "=C{$this->monthRowId}");
                $this->monthsAggregation->setCellValue("O{$this->monthRowId}", "=D{$this->monthRowId}");
                $this->monthsAggregation->setCellValue("P{$this->monthRowId}", "=N{$this->monthRowId}-L{$this->monthRowId}");
                $this->monthsAggregation->setCellValue("Q{$this->monthRowId}", "=C{$this->monthRowId}-M{$this->monthRowId}");
                $this->monthRowId++;
            }

            foreach (['B', 'D', 'F', 'G', 'H', 'I', 'J', 'M', 'O', 'Q'] as $column) {
                $this->monthsAggregation->setCellValue(
                    "{$column}{$this->monthRowId}",
                    "=SUM({$column}{$firstPosition}:{$column}" . ($this->monthRowId - 1) . ')'
                );
            }

            $this->monthRowId += 2;
            $this->monthsAggregation->setCellValue("L{$this->monthRowId}", "Zisk dle plánu");
            $this->monthsAggregation->setCellValue("M{$this->monthRowId}", "=M{$summaryRow}-B2");
            $this->monthsAggregation->setCellValue("O{$this->monthRowId}", "Ztráta");
            $this->monthsAggregation->setCellValue("P{$this->monthRowId}", "=M{$summaryRow}-O{$summaryRow}");
            $this->monthRowId += 3;
        }
    }

    private function getCurrencyFormat($currency)
    {
        static $mapping = [
            'CZK' => '# ##0 [$Kč-405]',
            'EUR' => NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE,
        ];
        return $mapping[$currency] ?? "#,##0.00 {$currency}";
    }

    private function indexToLetter($number)
    {
        return chr(substr("000" . ($number + 65), -3));
    }
}
