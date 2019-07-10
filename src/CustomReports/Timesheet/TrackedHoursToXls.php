<?php

namespace Costlocker\Reports\Custom\Timesheet;

use Costlocker\Reports\Transform\TransformToXls;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Costlocker\Reports\ReportSettings;

class TrackedHoursToXls extends TransformToXls
{
    private $xls;

    public function __invoke(array $report, ReportSettings $settings)
    {
        if (!$this->xls) {
            $this->xls = $this->createWorksheet($report['month']->format('Y'));
            $this->xls
                ->addRow([
                    $this->headerCell('CLIENT', 'd6dce5'),
                    $this->headerCell('PROJECT', 'd6dce5'),
                    $this->headerCell('ACTIVITY', 'd6dce5'),
                    $this->headerCell('ID', 'd6dce5'),
                    $this->headerCell('MONTH', 'd0cece'),
                    $this->headerCell('PERSON', 'fbe5d6'),
                    $this->headerCell('PERSON BonusID', 'fbe5d6'),
                    $this->headerCell('TRACKED', 'ffd966'),
                ])
                ->autosizeColumnsInCurrentRow()
                ->addRow([
                    $this->headerCell('', 'd6dce5'),
                    $this->headerCell('', 'd6dce5'),
                    $this->headerCell('', 'd6dce5'),
                    $this->headerCell('', 'd6dce5'),
                    $this->headerCell('', 'd0cece'),
                    $this->headerCell('', 'fbe5d6'),
                    $this->headerCell('', 'fbe5d6'),
                    $this->headerCell('[HRS]', 'ffd966'),
                ])
                ->mergeCells(["A1:A2", "B1:B2", "C1:C2", "D1:D2", "E1:E2", "F1:F2", "G1:G2"]);
        }
        $month = $this->cell("=DATE({$report['month']->format('Y, m, d')})")->format('MMMM');
        foreach ($report['people'] as $person) {
            if ($this->noColumn('activity', $settings)) {
                $this->xls->addRow([
                    $person['client'],
                    $person['project'],
                    implode(', ', array_keys($person['activities'])),
                    [$person['project_id'], NumberFormat::FORMAT_TEXT],
                    $month,
                    $person['person'],
                    $person['person_bonus_id'],
                    [$person['hrs_tracked_month'], NumberFormat::FORMAT_NUMBER_00],
                ]);
                continue;
            }
            foreach ($person['activities'] as $activity => $trackedTime) {
                $this->xls
                    ->addRow(
                        [
                            $person['client'],
                            $person['project'],
                            $activity,
                            [$person['project_id'], NumberFormat::FORMAT_TEXT],
                            $month,
                            $person['person'],
                            $person['person_bonus_id'],
                            [$trackedTime, NumberFormat::FORMAT_NUMBER_00],
                        ]
                    );
            }
        }
    }

    public function after(ReportSettings $settings)
    {
        $this->xls
            ->removeColumnIf('G', $this->noColumn('person_bonus_id', $settings))
            ->removeColumnIf('D', $this->noColumn('project_id', $settings))
            ->removeColumnIf('C', $this->noColumn('activity', $settings));
    }

    private function noColumn($column, ReportSettings $s)
    {
        return !in_array($column, $s->customConfig['extraColumns']);
    }
}
