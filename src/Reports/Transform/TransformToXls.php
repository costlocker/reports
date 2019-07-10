<?php

namespace Costlocker\Reports\Transform;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Costlocker\Reports\ReportSettings;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

abstract class TransformToXls implements Transformer
{
    private $spreadsheet;

    public function __construct(Spreadsheet $s)
    {
        $this->spreadsheet = $s;
    }

    abstract public function __invoke(array $report, ReportSettings $settings);

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) */
    public function after(ReportSettings $settings)
    {
    }

    protected function createWorksheet($title)
    {
        return new XlsBuilder($this->spreadsheet, $title);
    }

    /** @return XlsCellBuilder */
    protected function headerCell($title = '', $backgroundColor = 'transparent')
    {
        return (new XlsCellBuilder($title))
            ->defaultStyle($backgroundColor, Alignment::HORIZONTAL_CENTER);
    }

    /** @return XlsCellBuilder */
    protected function cell($title = '', $backgroundColor = null)
    {
        return (new XlsCellBuilder($title))
            ->defaultStyle($backgroundColor);
    }

    protected function getCurrencyFormat(ReportSettings $settings)
    {
        return XlsBuilder::getCurrencyFormat($settings->currency);
    }
}
