<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\IOFactory;

class GenerateReportCommand extends Command
{
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
        ]);

        $client = new CostlockerClient(new Client([
            'base_uri' => $apiHost,
            'http_errors' => false,
            'headers' => [
                'Api-Token' => $apiKey,
            ],
        ]));
        $projects = $client->projects();
        $people = $client->people();
        $timesheet = $client->timesheet($month);

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
        foreach ($people as $person) {
            $table->addRow([
                "<info>{$person['name']}</info>",
                '',
                '',
                $person['salary_hours'],
                $person['salary_amount'],
                '',
            ]);
            foreach ($person['projects'] as $idProject => $project) {
                $table->addRow([
                    $person['name'],
                    $projects[$idProject]['name'],
                    $projects[$idProject]['client'],
                    '',
                    '',
                    $project['hrs_tracked'],
                    $project['hrs_budget'],
                    "{$project['hrs_budget']} - ({$project['hrs_tracked']} - trackedHoursInMonth)",
                    'tracked - billable',
                    $project['client_rate'],
                ]);
            }
        }
        $table->render();

        $output->writeln(json_encode($timesheet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle($month->format('Y-m'));

        $rowId = 1;
        $addStyle = function (&$rowId, $backgroundColor = null) use ($worksheet) {
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
                ];
            }
            $worksheet->getStyle("A{$rowId}:J{$rowId}")->applyFromArray($styles);
            $rowId++;
        };
        
        foreach ($headers as $index => $header) {
            $worksheet->setCellValue("{$this->indexToLetter($index)}{$rowId}", $header);
        }
        $addStyle($rowId, 'CCCCCC');

        foreach ($people as $person) {
            $firstProjectRow = $rowId + 1;
            $lastProjectRow = $rowId + count($person['projects']);
            $rowData = [
                $person['name'],
                '',
                '',
                $person['salary_hours'],
                $person['salary_amount'],
                "=SUM(F{$firstProjectRow}:F{$lastProjectRow})",
                "=SUM(G{$firstProjectRow}:G{$lastProjectRow})",
                "=SUM(H{$firstProjectRow}:H{$lastProjectRow})",
                "=SUM(I{$firstProjectRow}:I{$lastProjectRow})",
                '',
            ];

            foreach ($rowData as $index => $value) {
                $worksheet->setCellValue("{$this->indexToLetter($index)}{$rowId}", $value);
            }
            $addStyle($rowId, 'bdd7ee');

            foreach ($person['projects'] as $idProject => $project) {
                $hoursTrackedInMonth = $project['hrs_tracked']; // load from timesheet
                $rowData = [
                    $person['name'],
                    $projects[$idProject]['name'],
                    $projects[$idProject]['client'],
                    '',
                    '',
                    $hoursTrackedInMonth,
                    $project['hrs_budget'],
                    "=MAX(0, G{$rowId}-({$project['hrs_tracked']}-F{$rowId}))",
                    "=MAX(0, F{$rowId}-H{$rowId})",
                    $project['client_rate'],
                ];
                foreach ($rowData as $index => $value) {
                    $worksheet->setCellValue("{$this->indexToLetter($index)}{$rowId}", $value);
                };
                $addStyle($rowId);
            }
        }

        foreach ($worksheet->getColumnDimensions() as $column) {
            $column->setAutoSize(true);
        }

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save("var/reports/{$month->format('Y-m')}.xlsx");
    }

    private function indexToLetter($number)
    {
        return chr(substr("000" . ($number + 65), -3));
    }
}
