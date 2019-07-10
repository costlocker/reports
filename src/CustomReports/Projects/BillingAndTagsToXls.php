<?php

namespace Costlocker\Reports\Custom\Projects;

use Costlocker\Reports\Transform\TransformToXls;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Costlocker\Reports\ReportSettings;

class BillingAndTagsToXls extends TransformToXls
{
    public function __invoke(array $report, ReportSettings $settings)
    {
        $currencyFormat = $this->getCurrencyFormat($settings);
        $xls = $this->createWorksheet('Projekty + Billing');
        $xls
            ->addRow(
                array_merge(
                    [
                        $this->headerCell('Klient', 'd6dce5'),
                        $this->headerCell('Projekt', 'd6dce5'),
                        $this->headerCell('Faktura', 'b6ef8f'),
                        $this->headerCell("Objem [{$settings->currency}]", 'b6ef8f'),
                        $this->headerCell("Vyfakturováno [{$settings->currency}]", 'b6ef8f'),
                        $this->headerCell('Datum fakturace', 'b6ef8f'),
                        $this->headerCell('Rok začátku', 'cccccc'),
                        $this->headerCell('Rok konce', 'cccccc'),
                        $this->headerCell('Tagy', 'cccccc'),
                    ],
                    array_values(array_map(
                        function ($tagName) {
                            return $this->headerCell($tagName, 'ffff00');
                        },
                        $report['tags']
                    ))
                )
            )
            ->autosizeColumnsInCurrentRow();

        foreach ($report['projects'] as $project) {
            $projectUrl = $settings->costlocker->projectUrl($project['project_id']);
            foreach ($project['billing']['invoices'] as $invoice) {
                $xls
                    ->addRow(array_merge(
                        [
                            $project['client'],
                            $this->cell($project['project'])->url($projectUrl),
                            $invoice['description'],
                            $this->cell($invoice['amount'])->format($currencyFormat),
                            $this->cell($invoice['is_sent'] ? $invoice['amount'] : 0)->format($currencyFormat),
                            $this->cell("=DATE({$invoice['date']->format('Y, m, d')})")
                                ->format(NumberFormat::FORMAT_DATE_YYYYMMDD2),
                            $this->cell("=DATE({$project['da_start']->format('Y, m, d')})")->format('YYYY'),
                            $this->cell("=DATE({$project['da_end']->format('Y, m, d')})")->format('YYYY'),
                            $project['tagNames'],
                        ],
                        array_values(array_map(
                            function ($tagId) use ($project) {
                                return in_array($tagId, $project['tagIds']) ? 1 : 0;
                            },
                            array_keys($report['tags'])
                        ))
                    ));
            }
        }
    }
}
