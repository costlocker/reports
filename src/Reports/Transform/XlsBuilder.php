<?php

namespace Costlocker\Reports\Transform;

use Costlocker\Reports\Config\Enum;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/** @SuppressWarnings(PHPMD.TooManyPublicMethods) */
class XlsBuilder
{
    private $worksheet;
    private $rowId;

    public function __construct(Worksheet $w, $rowId = 1)
    {
        $this->worksheet = $w;
        $this->rowId = $rowId;
    }

    public function addHeaders(array $headers)
    {
        $mainCells = [];
        $unitCells = [];
        foreach ($headers as $index => $header) {
            $column = $this->indexToLetter($index);
            $unitBuilder = null;
            if ($header instanceof XlsCellBuilder) {
                $cellBuilder = $header;
            } elseif (!is_array($header)) {
                $cellBuilder = (new XlsCellBuilder($header));
            } else { // legacy configuration from v1 and v2
                list($header, $backgroundColor, $unit) = $header + [0 => '', 1 => 'transparent', 2 => null];
                $cellBuilder = (new XlsCellBuilder($header))
                    ->defaultStyle($backgroundColor, Alignment::HORIZONTAL_CENTER);
                $unitBuilder = (new XlsCellBuilder($unit ? "[{$unit}]" : ''))
                    ->defaultStyle($backgroundColor, Alignment::HORIZONTAL_CENTER);
            }
            if (!$unitBuilder) {
                $unitBuilder = new XlsCellBuilder('');
                $this->mergeCells("{$column}{$this->getRowId(0)}:{$column}{$this->getRowId(1)}");
            }
            $mainCells[] = $cellBuilder;
            $unitCells[] = $unitBuilder;
        }

        return $this
            ->buildRowFromCells($mainCells)
            ->autosizeColumnsInCurrentRow()
            ->buildRowFromCells($unitCells);
    }

    public function addRow(array $data, $defaultBackgroundColorForNonBuilders = null)
    {
        $cells = [];
        foreach ($data as $value) {
            if ($value instanceof XlsCellBuilder) {
                $cellBuilder = $value;
            } elseif (!is_array($value)) {
                $cellBuilder = (new XlsCellBuilder($value))
                    ->defaultStyle($defaultBackgroundColorForNonBuilders);
            } else { // legacy configuration from v1 and v2
                list($value, $format, $conditionals, $url) = $value + [2 => [], 3 => null];
                $cellBuilder = (new XlsCellBuilder($value))
                    ->defaultStyle($defaultBackgroundColorForNonBuilders)
                    ->format($format)
                    ->url($url);
                if ($conditionals == 'HIGHLIGHT_STYLE') {
                    $cellBuilder->highlight();
                } elseif ($conditionals) {
                    $cellBuilder->evaluateNumber();
                }
            }
            $cells[] = $cellBuilder;
        }
        return $this->buildRowFromCells($cells);
    }

    private function buildRowFromCells(array $cells)
    {
        $this->setRow($this->rowId, $cells);
        $this->rowId++;
        return $this;
    }

    public function setRow($rowId, array $cells)
    {
        foreach ($cells as $index => $cellBuilder) {
            $cell = $this->worksheet->getCell("{$this->indexToLetter($index)}{$rowId}");
            $cellBuilder->build($cell);
        }
        return $this;
    }

    public function autosizeColumnsInCurrentRow(array $ignoredColumns = [])
    {
        foreach ($this->worksheet->getColumnIterator() as $column) {
            if (in_array($column->getColumnIndex(), $ignoredColumns)) {
                continue;
            }
            $this->autosizeColumns([$column->getColumnIndex()]);
        }
        return $this;
    }

    public function autosizeColumns(array $columns)
    {
        foreach ($columns as $column) {
            $this->worksheet->getColumnDimension($column)->setAutoSize(true);
        }
        return $this;
    }

    public function setCell($column, $rowIndex, $value)
    {
        if ($value instanceof XlsCellBuilder) {
            $cell = $this->worksheet->getCell("{$column}{$rowIndex}");
            $value->build($cell);
        } else {
            $this->worksheet->setCellValue("{$column}{$rowIndex}", $value);
        }
        return $this;
    }

    public function mergeColumnsInRow($firstColumn, $lastColumn)
    {
        return $this->mergeCells("{$firstColumn}{$this->rowId}:{$lastColumn}{$this->rowId}");
    }

    public function mergeCells($range)
    {
        $ranges = (array) $range;
        foreach ($ranges as $r) {
            $this->worksheet->mergeCells($r);
        }
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

    public function indexToLetter($number)
    {
        if ($number > 25) {
            $prefix = 'A';
            $number -= 26;
        } else {
            $prefix = '';
        }
        return $prefix . chr(substr("000" . ($number + 65), -3));
    }

    public static function getCurrencyFormat($currency)
    {
        static $mapping = [
            Enum::CURRENCY_CZK => '# ##0 [$Kč-405]',
            Enum::CURRENCY_EUR => NumberFormat::FORMAT_CURRENCY_EUR_SIMPLE,
        ];
        return $mapping[$currency] ?? "#,##0.00 {$currency}";
    }
}
