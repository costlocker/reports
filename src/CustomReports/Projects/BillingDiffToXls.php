<?php

namespace Costlocker\Reports\Custom\Projects;

use Costlocker\Reports\Transform\TransformToXls;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Costlocker\Reports\ReportSettings;

class BillingDiffToXls extends TransformToXls
{
    public function __invoke(array $report, ReportSettings $settings)
    {
        $currencyFormat = $this->getCurrencyFormat($settings);
        $xls = $this->appendWorksheet($report['dateChange']->format('Y-m'));
        $xls
            ->setRow(
                1,
                [
                    $this->headerCell('Client', 'd6dce5'),
                    $this->headerCell('Project', 'd6dce5'),
                    $this->headerCell('Date change', 'fbe5d6'),
                    $this->headerCell('Change', 'fbe5d6'),
                    $this->headerCell('CHANGED Invoiced?', 'b6ef8f'),
                    $this->headerCell('AFTER Invoiced?', 'ffa500'),
                    $this->headerCell('BEFORE Invoiced?', 'cccccc'),
                    $this->headerCell("CHANGED Amount [{$settings->currency}]", 'b6ef8f'),
                    $this->headerCell("AFTER Amount [{$settings->currency}]", 'ffa500'),
                    $this->headerCell("BEFORE Amount [{$settings->currency}]", 'cccccc'),
                    $this->headerCell('CHANGED Date', 'b6ef8f'),
                    $this->headerCell('AFTER Date', 'ffa500'),
                    $this->headerCell('BEFORE Date', 'cccccc'),
                    $this->headerCell('CHANGED Description', 'b6ef8f'),
                    $this->headerCell('AFTER Description', 'ffa500'),
                    $this->headerCell('BEFORE Description', 'cccccc'),
                    $this->headerCell('ID', 'd6dce5'),
                ]
            )
            ->hideColumn('F')
            ->hideColumn('G')
            ->hideColumn('I')
            ->hideColumn('J')
            ->hideColumn('L')
            ->hideColumn('M')
            ->hideColumn('O')
            ->hideColumn('P')
            ->autosizeColumns(range('A', 'Q'));
        if ($xls->getRowId() == 1) {
            $xls->skipRows(1);// skip to second row in new spreadsheet
        }

        foreach ($report['billing'] as $invoice) {
            $diff = $this->billingToCells($invoice['diff'], $currencyFormat);
            $before = $this->billingToCells($invoice['before'], $currencyFormat);
            $after = $this->billingToCells($invoice['after'], $currencyFormat);
            $xls
                ->addRow([
                    $invoice['client'],
                    $this->cell($invoice['project'])->url($invoice['url']),
                    $this->cell("=DATE({$report['dateChange']->format('Y, m, d')})")
                        ->format(NumberFormat::FORMAT_DATE_YYYYMMDD2),
                    $invoice['action'],
                    $diff['is_sent'],
                    $after['is_sent'],
                    $before['is_sent'],
                    $diff['amount'],
                    $after['amount'],
                    $before['amount'],
                    $diff['date'],
                    $after['date'],
                    $before['date'],
                    $diff['description'],
                    $after['description'],
                    $before['description'],
                    $invoice['id'],
                ]);
        }
    }

    private function billingToCells(array $invoice, $currencyFormat)
    {
        return [
            'description' => $invoice['description'],
            'amount' => $this->cell($invoice['amount'])->format($currencyFormat),
            'date' => !$invoice['date']
                ? null
                : $this->cell("=DATE({$invoice['date']->format('Y, m, d')})")
                    ->format(NumberFormat::FORMAT_DATE_YYYYMMDD2),
            'is_sent' => $invoice['is_sent'] === null ? null : $this->cell($invoice['is_sent'] ? 'YES' : 'NO'),
        ];
    }
}
