<?php

namespace Costlocker\Reports\Custom\Timesheet;

use Costlocker\Reports\Transform\TransformToXls;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Costlocker\Reports\ReportSettings;

class WeeklyTimesheetToXls extends TransformToXls
{
    public function __invoke(array $report, ReportSettings $settings)
    {
        $xls = $this->createWorksheet($report['monday']->format('Y_W'));
        $xls
            ->addRow([$this->cell($settings->title, 'bdd7ee')])
            ->addRow([$this->cell("Týmy: " . implode(', ', $settings->customConfig['groupNames']), 'fbe5d6')])
            ->skipRows(1)
            ->addRow([
                $this->headerCell('Datum', 'd0cece'),
                $this->headerCell('Projekt', 'd6dce5'),
                $this->headerCell('Klient', 'd6dce5'),
                $this->headerCell('Osoba', 'd6dce5'),
                $this->headerCell('Aktivita', 'd6dce5'),
                $this->headerCell('Natrackováno', 'ffd966', 'HRS'),
                $this->headerCell('Tým', 'fbe5d6'),
            ])
            ->autosizeColumnsInCurrentRow();

        foreach ($report['entries'] as $entry) {
            $xls->addRow([
                $this->cell("=DATE({$entry['date']->format('Y, m, d')})")->format('dd.mm.YYYY'),
                $entry['project'] ?: '[Nepřiřazený projekt]',
                $entry['client'] ?: '[Nepřiřazený klient]',
                $entry['person'],
                $entry['activity'] ?: '[Nepřiřazená činnost]',
                $this->cell("={$entry['scs_tracked']} / 3600.0")->format(NumberFormat::FORMAT_NUMBER_00),
                $entry['team'],
            ]);
        }
    }
}
