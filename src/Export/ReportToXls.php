<?php

namespace Costlocker\Reports\Export;

use Symfony\Component\Console\Output\OutputInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Costlocker\Reports\Mailer;
use Costlocker\Reports\CostlockerReport;
use Costlocker\Reports\ReportSettings;

class ReportToXls
{
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function __invoke(CostlockerReport $report, OutputInterface $output, ReportSettings $settings)
    {
        $currencyFormat = '# ##0 [$Kč-405]';
        $headers = [
            ['IS PROFITABLE?', 'd6dce5'],
            ['NAME', 'd6dce5'],
            ['PROJECT', 'd6dce5'],
            ['CLIENT', 'd6dce5'],
            ['Contracted', 'd0cece', 'HRS'],
            ['Wages', 'd0cece', 'CZK'],
            ['Tracked', 'd0cece', 'HRS'],
            ['Estimate', 'd0cece', 'HRS'],
            ['BILLABLE', 'ffd966', 'hrs'],
            ['NON-BILLABLE', 'd0cece', 'hrs'],
            ['CLIENT RATE', 'ffd966', 'CZK'],
            ['INVOICED PRICE', 'ffd966', 'CZK'],
            ['SALES', 'fbe5d6', '%'],
            ['NO SALES', 'fbe5d6', '%'],
            ['PROFITABILITY', 'fbe5d6', 'CZK'],
            ["NON-BILLABLE On internal projects", 'f4b183', 'CZK'],
            ["NON-BILLABLE On billable projects", 'f4b183', 'CZK'],
            ["TOTAL Non-Billable", 'ed7d31', 'CZK'],
        ];

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
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
            $worksheet->getStyle("A{$rowId}:R{$rowId}")->applyFromArray($styles);
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
        }
        $addStyle($rowId, 'transparent', Alignment::HORIZONTAL_CENTER);
        $addStyle($rowId, 'transparent', Alignment::HORIZONTAL_CENTER);

        foreach ($report->getActivePeople() as $person) {
            $summaryRow = $rowId;
            $firstProjectRow = $rowId + 1;
            $lastProjectRow = $rowId + count($person['projects']);
            $rowData = [
                "=IF(O{$summaryRow}>0, \"YES\", \"NO\")",
                $person['name'],
                '',
                '',
                $settings->hardcodedHours ?: $person['salary_hours'],
                [$person['salary_amount'], $currencyFormat],
                ["=SUM(G{$firstProjectRow}:G{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                ["=SUM(H{$firstProjectRow}:H{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                ["=SUM(I{$firstProjectRow}:I{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                ["=SUM(J{$firstProjectRow}:J{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                '',
                ["=SUM(L{$firstProjectRow}:L{$lastProjectRow})", $currencyFormat],
                ["=SUM(M{$firstProjectRow}:M{$lastProjectRow})", NumberFormat::FORMAT_PERCENTAGE_00],
                ["=1-M{$summaryRow}", NumberFormat::FORMAT_PERCENTAGE_00],
                ["=L{$summaryRow}-F{$summaryRow}", $currencyFormat],
                ["=SUM(P{$firstProjectRow}:P{$lastProjectRow})", $currencyFormat],
                ["=SUM(Q{$firstProjectRow}:Q{$lastProjectRow})", $currencyFormat],
                ["=P{$summaryRow}+Q{$summaryRow}", $currencyFormat],
            ];

            $setRowData($rowId, $rowData);
            $addStyle($rowId, 'bdd7ee');

            foreach ($person['projects'] as $project) {
                $isBillableProject = $project['client_rate'] > 0;
                $nonBillableMoney = ["=-1*((\$F\${$summaryRow}/\$E\${$summaryRow})*J{$rowId})", $currencyFormat];
                $remainingBillable =
                    "H{$rowId}-({$project['hrs_tracked_total']}-{$project['hrs_tracked_after_month']}-G{$rowId})";
                $rowData = [
                    '',
                    $person['name'],
                    $project['name'],
                    $project['client'],
                    '',
                    '',
                    [$project['hrs_tracked_month'], NumberFormat::FORMAT_NUMBER_00],
                    [$project['hrs_budget'], NumberFormat::FORMAT_NUMBER_00],
                    [
                        "=MIN(G{$rowId}, MAX(0, {$remainingBillable}))",
                        NumberFormat::FORMAT_NUMBER_00
                    ],
                    ["=MAX(0, G{$rowId}-I{$rowId})", NumberFormat::FORMAT_NUMBER_00],
                    [$project['client_rate'], $currencyFormat],
                    ["=I{$rowId}*K{$rowId}", $currencyFormat],
                    ["=I{$rowId}/\$E\${$summaryRow}", NumberFormat::FORMAT_PERCENTAGE_00],
                    '',
                    '',
                    $isBillableProject ? '' : $nonBillableMoney,
                    $isBillableProject ? $nonBillableMoney : '',
                ];
                $setRowData($rowId, $rowData);
                $addStyle($rowId);
            }
        }

        foreach ($worksheet->getColumnDimensions() as $column) {
            $column->setAutoSize(true);
        }

        $this->export($report, $spreadsheet, $output, $settings->email);
    }

    private function export(CostlockerReport $report, Spreadsheet $spreadsheet, $output, $recipient)
    {
        $xlsFile = "var/reports/{$report->selectedMonth->format('Y-m')}.xlsx";
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsFile);

        $wasSent = $this->mailer->__invoke($recipient, $xlsFile, $report->selectedMonth);
        if ($wasSent) {
            unlink($xlsFile);
            $output->writeln('<comment>E-mail was sent!</comment>');
        } else {
            $output->writeln('<error>E-mail was not sent!</error>');
        }
    }

    private function indexToLetter($number)
    {
        return chr(substr("000" . ($number + 65), -3));
    }
}
