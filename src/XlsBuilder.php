<?php

namespace Costlocker\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class XlsBuilder
{
    private $worksheet;
    private $rowId = 1;

    public function __construct(Spreadsheet $spreadsheet, $title)
    {
        $this->worksheet = $spreadsheet->createSheet();
        $this->worksheet->setTitle($title);
    }

    public function addHeaders(array $headers)
    {
        $unitRowId = $this->rowId + 1;
        foreach ($headers as $index => $header) {
            $column = $this->indexToLetter($index);
            list($header, $backgroundColor, $unit) = $header + [0 => '', 1 => 'transparent', 2 => null];
            if ($unit) {
                $this->worksheet
                    ->setCellValue("{$column}{$this->rowId}", $header)
                    ->setCellValue("{$column}{$unitRowId}", "[{$unit}]");
            } else {
                $this->worksheet
                    ->setCellValue("{$column}{$this->rowId}", $header)
                    ->mergeCells("{$column}{$this->rowId}:{$column}{$unitRowId}");
            }
            $this->worksheet->getStyle("{$column}{$this->rowId}:{$column}{$unitRowId}")->applyFromArray([
                'fill' => [
                    'type' => Fill::FILL_SOLID,
                    'startcolor' => array(
                        'rgb' => $backgroundColor
                    ),
                ],
            ]);
            $this->worksheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        return $this
            ->addStyle(0, count($headers), 'transparent', Alignment::HORIZONTAL_CENTER)
            ->addStyle(0, count($headers), 'transparent', Alignment::HORIZONTAL_CENTER);
    }

    public function addRow(array $data, $backgroundColor = null, $alignment = null)
    {
        foreach ($data as $index => $value) {
            if (is_array($value)) {
                list($value, $format, $conditionals) = $value + [2 => []];
                $coordinate = "{$this->indexToLetter($index)}{$this->rowId}";
                $cell = $this->worksheet->setCellValue($coordinate, $value, true);
                $cell->getStyle()
                    ->setConditionalStyles($conditionals)
                    ->getNumberFormat()->setFormatCode($format);
            } else {
                $this->worksheet->setCellValue("{$this->indexToLetter($index)}{$this->rowId}", $value);
            }
        }
        reset($data);
        return $this->addStyle(key($data), count($data), $backgroundColor, $alignment);
    }

    private function addStyle($firstColumnIndex, $columnsCount, $backgroundColor = null, $alignment = null)
    {
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

        $firstColumn = $this->indexToLetter($firstColumnIndex);
        $lastColumn = $this->indexToLetter($firstColumnIndex + $columnsCount - 1);
        $this->worksheet->getStyle("{$firstColumn}{$this->rowId}:{$lastColumn}{$this->rowId}")
            ->applyFromArray($styles);
        $this->rowId++;

        return $this;
    }

    public function mergeCells($firstColumn, $lastColumn)
    {
        $this->worksheet->mergeCells("{$firstColumn}{$this->rowId}:{$lastColumn}{$this->rowId}");
        return $this;
    }

    public function setRowVisibility($isVisible)
    {
        $this->worksheet->getRowDimension($this->rowId)->setVisible($isVisible);
        return $this;
    }

    public function hideColumn($column)
    {
        $this->worksheet->getColumnDimension($column)->setVisible(false);
        return $this;
    }

    public function removeColumnIf($column, $isRemoved)
    {
        if ($isRemoved) {
            $this->worksheet->removeColumn($column);
        }
        return $this;
    }

    public function skipRows($rowsCount)
    {
        $this->rowId = $this->getRowId($rowsCount);
        return $this;
    }

    public function getRowId($nextRowsCount = 0)
    {
        return $this->rowId + $nextRowsCount;
    }

    public function getWorksheetReference()
    {
        return "'{$this->worksheet->getTitle()}'!";
    }

    public function compareToZero($operator, $color)
    {
        $conditional = new Conditional();
        $conditional
            ->setConditionType(Conditional::CONDITION_CELLIS)
            ->setOperatorType($operator)
            ->addCondition('0')
            ->getStyle()->applyFromArray([
                'font' => [
                    'color' => [
                        'rgb' => $color
                    ]
                ],
            ]);
        return $conditional;
    }

    private function indexToLetter($number)
    {
        return chr(substr("000" . ($number + 65), -3));
    }

    public static function getCurrencyFormat($currency)
    {
        static $mapping = [
            'CZK' => '# ##0 [$Kč-405]',
            'EUR' => NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE,
        ];
        return $mapping[$currency] ?? "#,##0.00 {$currency}";
    }
}
