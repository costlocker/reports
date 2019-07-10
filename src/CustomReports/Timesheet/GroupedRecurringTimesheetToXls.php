<?php

namespace Costlocker\Reports\Custom\Timesheet;

use Costlocker\Reports\Transform\TransformToXls;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Costlocker\Reports\ReportSettings;

class GroupedRecurringTimesheetToXls extends TransformToXls
{
    /** @SuppressWarnings(PHPMD.ExcessiveMethodLength) */
    public function __invoke(array $report, ReportSettings $settings)
    {
        $xls = $this->createWorksheet($report['month']->format('Y-m'));
        $xls
            ->addRow([
                $this->headerCell('RECURRING', 'ffd966'),
                $this->cell($report['recurring']['template']['name'], 'ffd966')
                    ->url($report['recurring']['template']['id']),
            ])
            ->addRow([
                $this->headerCell('PROJECT', 'ffd966'),
                $this->cell($report['recurring']['instance']['name'], 'ffd966')
                    ->url($report['recurring']['instance']['id']),
            ])
            ->skipRows(2)
            ->addRow(
                [
                    'Pozice',
                    'Týden',
                    'Jméno',
                    'Čas v hodinách',
                    'Alokace týden',
                    '',
                    '',
                    'Alokace měsíc',
                    'Využito',
                    'Stav +-',
                    'Využito v %',
                ],
                'f4b183'
            )
            ->autosizeColumnsInCurrentRow(['C']);

        $sumWeekRows = function (array $data, $column) use ($xls) {
            $rows = [];
            $rowNumber = $xls->getRowId(2);
            foreach ($data['weeks'] as $weekDays) {
                $rows[] = "{$column}{$rowNumber}";
                $rowNumber += count($weekDays) + 1;
            }
            return $this->cell("=SUM(" . implode(',', $rows) . ")", 'd0cece')
                ->format(NumberFormat::FORMAT_NUMBER_00);
        };

        foreach ($report['activities'] as $activity => $data) {
            $people = array_unique($data['people']);
            $xls
                ->addRow(
                    [
                        $activity,
                        '',
                        '',
                        '',
                        '',
                        '',
                        '',
                        $sumWeekRows($data, 'E'),
                        $sumWeekRows($data, 'D'),
                        $this->cell("=I{$xls->getRowId()}-H{$xls->getRowId()}", 'd0cece')
                            ->format(NumberFormat::FORMAT_NUMBER_00),
                        $this->cell("=I{$xls->getRowId()}/H{$xls->getRowId()}", 'd0cece')
                            ->format(NumberFormat::FORMAT_PERCENTAGE_00),
                    ],
                    'd0cece'
                )
                ->addRow(
                    [
                        '',
                        '',
                        implode(', ', $people),
                        '',
                        '',
                    ],
                    'd0cece'
                );
            foreach ($data['weeks'] as $weekNumber => $days) {
                $xls
                    ->addRow(
                        [
                            '',
                            "w{$weekNumber}",
                            '',
                            $this->cell("=SUM(D{$xls->getRowId(1)}:D{$xls->getRowId(count($days))})", 'd6dce5')
                                ->format(NumberFormat::FORMAT_NUMBER_00),
                            $this->cell("=SUM(E{$xls->getRowId(1)}:E{$xls->getRowId(count($days))})", 'd6dce5')
                                ->format(NumberFormat::FORMAT_NUMBER_00),
                        ],
                        'd6dce5'
                    );
                foreach ($days as $dayNumber => $dayData) {
                    $day = \DateTime::createFromFormat('Y-m-d', $report['month']->format("Y-m-{$dayNumber}"));
                    $allocatedHours = $dayData['is_working_day'] ? $data['daily_hours'] : 0;
                    $xls
                        ->addRow([
                            $day->format('j.n'),
                            $this->cell("=DATE({$day->format('Y, m, d')})")
                                ->format('DDDD'),
                            $this->buildDescriptions($dayData),
                            $this->cell("={$dayData['scs_tracked']}/3600")
                                ->format(NumberFormat::FORMAT_NUMBER_00),
                            $this->cell("={$allocatedHours}")
                                ->format(NumberFormat::FORMAT_NUMBER_00),
                        ]);
                }
            }
            $xls->skipRows(1);
        }

        $xls->autosizeColumns(['A', 'B', 'D', 'E', 'H', 'I', 'J', 'K']);
    }

    private function buildDescriptions(array $dayData)
    {
        $prefix = '';
        if ($dayData['is_holiday']) {
            $prefix = '[Public holiday] ';
        } elseif ($dayData['is_weekend']) {
            $prefix = '[Weekend] ';
        }

        $descriptions = implode('; ', $dayData['descriptions']);
        return "{$prefix}{$descriptions}";
    }
}
