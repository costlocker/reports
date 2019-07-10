<?php

namespace Costlocker\Reports\Custom\Projects;

use Costlocker\Reports\Transform\TransformToXls;
use Costlocker\Reports\ReportSettings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ProjectsOverviewToXls extends TransformToXls
{
    public function __invoke(array $projects, ReportSettings $settings)
    {
        $xls = $this->createWorksheet('COSTLOCKER');
        $xls
            ->mergeColumnsInRow('A', 'E')
            ->mergeColumnsInRow('F', 'G')
            ->mergeColumnsInRow('H', 'J')
            ->mergeColumnsInRow('K', 'T')
            ->addRow([
                $this->headerCell('Projekt', '0000ff'),
                '',
                '',
                '',
                '',
                $this->headerCell('Fakturace', 'ffa500'),
                '',
                $this->headerCell('Projektové náklady', 'ffff00'),
                '',
                '',
                $this->headerCell('Lidé', '00ff00'),
            ])
            ->addRow([
                $this->headerCell('ID', '0000ff'),
                $this->headerCell('Status', '0000ff'),
                $this->headerCell('Klient', '0000ff'),
                $this->headerCell('Projekt', '0000ff'),
                $this->headerCell('Rok začátku', '0000ff'),
                $this->headerCell('Vyfakturováno', 'ffa500'),
                $this->headerCell('Nevyfakturováno', 'ffa500'),
                $this->headerCell('Náklady', 'ffff00'),
                $this->headerCell('Příjmy', 'ffff00'),
                $this->headerCell('Zisk', 'ffff00'),
                $this->headerCell('Rozpočet', '00ff00'),
                $this->headerCell('Sleva', '00ff00'),
                $this->headerCell('Příjmy', '00ff00'),
                $this->headerCell('Náklady', '00ff00'),
                $this->headerCell('Zisk', '00ff00'),
                $this->headerCell('Odhadované hodiny', 'c4ffc4'),
                $this->headerCell('Placené hodiny (natrackované i zbývající odhad)', 'c4ffc4'),
                $this->headerCell('Natrackované hodiny', 'c4ffc4'),
                $this->headerCell('Natrackované / Placené', 'c4ffc4'),
                $this->headerCell('Typ rozpočtu', 'c4ffc4'),
            ])
            ->autosizeColumnsInCurrentRow();

        foreach ($projects as $project) {
            $xls->addRow([
                $project['project_id'],
                $project['state'],
                $this->cell($project['client']),
                $project['name'],
                [
                    "=DATE({$project['dates']['start']->format('Y, m, d')})",
                    'YYYY',
                ],
                $this->cell("=I{$xls->getRowId()}+M{$xls->getRowId()}")
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell("=F{$xls->getRowId()}-{$project['financialMetrics']['billingBilled']}")
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell($project['financialMetrics']['expensesCosts'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell($project['financialMetrics']['expensesRevenue'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell("=I{$xls->getRowId()}-H{$xls->getRowId()}")
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell($project['financialMetrics']['peopleRevenue'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell($project['financialMetrics']['peopleDiscount'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                ["=K{$xls->getRowId()}-L{$xls->getRowId()}", NumberFormat::FORMAT_NUMBER_00],
                $this->cell($project['financialMetrics']['peopleCosts'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell("=M{$xls->getRowId()}-N{$xls->getRowId()}")
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell($project['hours']['estimated'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell($project['hours']['billable'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell($project['hours']['tracked'])
                    ->format(NumberFormat::FORMAT_NUMBER_00),
                $this->cell("=IF(Q{$xls->getRowId()} <> 0, R{$xls->getRowId()}/Q{$xls->getRowId()}, \"\")")
                    ->format(NumberFormat::FORMAT_PERCENTAGE_00),
                $project['budget_type'],
            ]);
        }
    }
}
