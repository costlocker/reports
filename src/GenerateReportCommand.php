<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GenerateReportCommand extends Command
{
    private $mailer;

    public function __construct(Mailer $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this
            ->setName('report')
            ->addOption('date', 'd', InputOption::VALUE_REQUIRED, 'Select month', 'previous month')
            ->addOption('host', 'a', InputOption::VALUE_REQUIRED, 'apiUrl|apiKey')
            ->addOption('email', 'e', InputOption::VALUE_OPTIONAL, 'Report recipients');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $month = new \DateTime($input->getOption('date'));
        list($apiHost, $apiKey) = explode('|', $input->getOption('host'));
        $email = $input->getOption('email');

        $output->writeln([
            "<comment>Report</comment>",
            "<info>Month:</info> {$month->format('Y-m')}",
            "<info>API Url:</info> {$apiHost}",
            "<info>API Key:</info> {$apiKey}",
            "<info>E-mail Recipients:</info> {$email}",
            '',
        ]);

        $client = CostlockerClient::build($apiHost, $apiKey);
        $report = $client($month);

        if ($email) {
            $this->emailRenderer($report, $email, $output);
        } else {
            $this->consoleRenderer($report, $output);
        }
    }

    protected function consoleRenderer(CostlockerReport $report, OutputInterface $output)
    {
        $headers = [
            'Person',
            'Project',
            'Client',
            'Hours',
            'Salary',
            'Tracked Hours',
            'Estimated Hours',
            'Billable',
            'Non-Billable',
            'Client Rate',
        ];

        $table = new Table($output);
        $table->setHeaders($headers);
        foreach ($report->getActivePeople() as $person) {
            $table->addRow([
                "<info>{$person['name']}</info>",
                '',
                '',
                $person['salary_hours'],
                $person['salary_amount'],
                '',
            ]);
            foreach ($person['projects'] as $project) {
                $table->addRow([
                    $person['name'],
                    $project['name'],
                    $project['client'],
                    '',
                    '',
                    $project['hrs_tracked_month'],
                    $project['hrs_budget'],
                    "{$project['hrs_budget']} - ({$project['hrs_tracked_total']} - "
                        . "{$project['hrs_tracked_after_month']} - {$project['hrs_tracked_month']})",
                    'tracked - billable',
                    $project['client_rate'],
                ]);
            }
        }
        $table->render();
    }

    protected function emailRenderer(CostlockerReport $report, $recipient, OutputInterface $output)
    {
        $headers = [
            'IS PROFITABLE?',
            'NAME',
            'PROJECT',
            'CLIENT',
            ['Contracted', 'HRS'],
            ['Wages', 'CZK'],
            ['Tracked', 'HRS'],
            ['Estimate', 'HRS'],
            ['BILLABLE', 'hrs'],
            ['NON-BILLABLE', 'hrs'],
            ['CLIENT RATE', 'CZK'],
            ['INVOICED PRICE', 'CZK'],
            ['SALES', '%'],
            ['NO SALES', '%'],
            ['PROFITABILITY', 'CZK'],
            ["NON-BILLABLE\nOn internal projects", 'CZK'],
            ["NON-BILLABLE\nOn billable projects", 'CZK'],
            ["TOTAL\nNon-Billable", 'CZK'],
        ];

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle($report->selectedMonth->format('Y-m'));

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
                            'rgb' => $backgroundColor
                        ),
                    ],
                    'alignment' => [
                        'horizontal' => $alignment,
                    ],
                ];
            }
            $worksheet->getStyle("A{$rowId}:R{$rowId}")->applyFromArray($styles);
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
            if (is_array($header)) {
                $worksheet->setCellValue("{$column}{$rowId}", $header[0]);
                $worksheet->setCellValue("{$column}{$unitRowId}", "[{$header[1]}]");
            } else {
                $worksheet->setCellValue("{$column}{$rowId}", $header);
                $worksheet->mergeCells("{$column}{$rowId}:{$column}{$unitRowId}");
            }
        }
        $addStyle($rowId, 'CCCCCC', Alignment::HORIZONTAL_CENTER);
        $addStyle($rowId, 'CCCCCC', Alignment::HORIZONTAL_CENTER);

        foreach ($report->getActivePeople() as $person) {
            $summaryRow = $rowId;
            $firstProjectRow = $rowId + 1;
            $lastProjectRow = $rowId + count($person['projects']);
            $rowData = [
                "=IF(O{$summaryRow}>0, \"YES\", \"NO\")",
                $person['name'],
                '',
                '',
                $person['salary_hours'],
                $person['salary_amount'],
                ["=SUM(G{$firstProjectRow}:G{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                ["=SUM(H{$firstProjectRow}:H{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                ["=SUM(I{$firstProjectRow}:I{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                ["=SUM(J{$firstProjectRow}:J{$lastProjectRow})", NumberFormat::FORMAT_NUMBER_00],
                '',
                "=SUM(L{$firstProjectRow}:L{$lastProjectRow})",
                ["=SUM(M{$firstProjectRow}:M{$lastProjectRow})", NumberFormat::FORMAT_PERCENTAGE_00],
                ["=1-M{$summaryRow}", NumberFormat::FORMAT_PERCENTAGE_00],
                "=L{$summaryRow}-F{$summaryRow}",
                "=SUM(P{$firstProjectRow}:P{$lastProjectRow})",
                "=SUM(Q{$firstProjectRow}:Q{$lastProjectRow})",
                "=P{$summaryRow}+Q{$summaryRow}",
            ];

            $setRowData($rowId, $rowData);
            $addStyle($rowId, 'bdd7ee');

            foreach ($person['projects'] as $project) {
                $isBillableProject = $project['client_rate'] > 0;
                $nonBillableMoney = "=-1*((\$F\${$summaryRow}/\$E\${$summaryRow})*J{$rowId})";
                $rowData = [
                    '',
                    $person['name'],
                    $project['name'],
                    $project['client'],
                    '',
                    '',
                    [$project['hrs_tracked_month'], NumberFormat::FORMAT_NUMBER_00],
                    [$project['hrs_budget'], NumberFormat::FORMAT_NUMBER_00],
                    [
                        "=MAX(0, H{$rowId}-"
                            . "({$project['hrs_tracked_total']}-{$project['hrs_tracked_after_month']}-G{$rowId}))",
                        NumberFormat::FORMAT_NUMBER_00
                    ],
                    ["=MAX(0, G{$rowId}-I{$rowId})", NumberFormat::FORMAT_NUMBER_00],
                    $project['client_rate'],
                    "=I{$rowId}*K{$rowId}",
                    ["=I{$rowId}/\$E\${$summaryRow}", NumberFormat::FORMAT_PERCENTAGE_00],
                    '',
                    '',
                    $isBillableProject ? '' : $nonBillableMoney,
                    $isBillableProject ? $nonBillableMoney : '',
                ];
                $setRowData($rowId, $rowData);
                $addStyle($rowId);
            }
        }

        foreach ($worksheet->getColumnDimensions() as $column) {
            $column->setAutoSize(true);
        }

        $xlsFile = "var/reports/{$report->selectedMonth->format('Y-m')}.xlsx";
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsFile);

        $wasSent = $this->mailer->__invoke($recipient, $xlsFile, $report->selectedMonth);
        if ($wasSent) {
            unlink($xlsFile);
            $output->writeln('<comment>E-mail was sent!</comment>');
        } else {
            $output->writeln('<error>E-mail was not sent!</error>');
        }
    }

    private function indexToLetter($number)
    {
        return chr(substr("000" . ($number + 65), -3));
    }
}
