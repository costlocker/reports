<?php

namespace Costlocker\Reports\Inspiro;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Costlocker\Reports\ReportSettings;

class InspiroToXls
{
    private $spreadsheet;

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
    }

    public function __invoke(InspiroReport $report, ReportSettings $settings)
    {
        $currencyFormat = $this->getCurrencyFormat($settings->currency);
        $headers = [
            ['Client', 'd6dce5'],
            ['Projects (finished projects)', 'ffd966', 'count'],
            ['Revenue (finished projects)', 'ffd966', $settings->currency],
            ['Revenue - Project Expenses (finished projects)', 'ffd966', $settings->currency],
            ['Profit (finished projects)', 'ffd966', $settings->currency],
            ['Projects (running projects)', 'fbe5d6', 'count'],
            ['Revenue (running projects)', 'fbe5d6', $settings->currency],
            ['Revenue - Project Expenses (running projects)', 'fbe5d6', $settings->currency],
            ['Profit (running projects)', 'fbe5d6', $settings->currency],
        ];

        $worksheet = $this->spreadsheet->createSheet();
        $worksheet->setTitle($report->lastDay->format('Y'));

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
            $worksheet->getStyle("A{$rowId}:I{$rowId}")->applyFromArray($styles);
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

        foreach ($report->getActiveClients() as $client => $billing) {
            $rowData = [
                $client,
                [$billing['finished']['projects'], NumberFormat::FORMAT_NUMBER],
                [$billing['finished']['revenue'], $currencyFormat],
                ["=C{$rowId}-{$billing['finished']['expenses']}", $currencyFormat],
                ['', $currencyFormat],
                [$billing['running']['projects'], NumberFormat::FORMAT_NUMBER],
                [$billing['running']['revenue'], $currencyFormat],
                ["=G{$rowId}-{$billing['running']['expenses']}", $currencyFormat],
                ['', $currencyFormat],
            ];

            $setRowData($rowId, $rowData);
            $addStyle($rowId, 'transparent');
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
