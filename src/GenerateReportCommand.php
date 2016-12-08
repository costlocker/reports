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
use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;

class GenerateReportCommand extends Command
{
    private $mailer;

    public function __construct(Swift_Mailer $mailer)
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
        ]);

        $client = new CostlockerClient(new Client([
            'base_uri' => $apiHost,
            'http_errors' => false,
            'headers' => [
                'Api-Token' => $apiKey,
            ],
        ]));

        $report = $client($month);

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
        foreach ($report->getPeople() as $personId => $person) {
            $table->addRow([
                "<info>{$person['name']}</info>",
                '',
                '',
                $person['salary_hours'],
                $person['salary_amount'],
                '',
            ]);
            foreach ($report->getPersonProjects($personId) as $idProject => $project) {
                $table->addRow([
                    $person['name'],
                    $report->getProjectName($idProject),
                    $report->getProjectClient($idProject),
                    '',
                    '',
                    $project['hrs_tracked_total'],
                    $project['hrs_budget'],
                    "{$project['hrs_budget']} - ({$project['hrs_tracked_total']} - trackedHoursInMonth)",
                    'tracked - billable',
                    $project['client_rate'],
                ]);
            }
        }
        $table->render();

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

        foreach ($report->getPeople() as $personId => $person) {
            $firstProjectRow = $rowId + 1;
            $personProjects = $report->getPersonProjects($personId);
            $lastProjectRow = $rowId + count($personProjects);
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

            foreach ($personProjects as $idProject => $project) {
                $hoursTrackedInMonth = $project['hrs_tracked_total']; // load from timesheet
                $rowData = [
                    $person['name'],
                    $report->getProjectName($idProject),
                    $report->getProjectClient($idProject),
                    '',
                    '',
                    $hoursTrackedInMonth,
                    $project['hrs_budget'],
                    "=MAX(0, G{$rowId}-({$project['hrs_tracked_total']}-F{$rowId}))",
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

        $xlsFile = "var/reports/{$month->format('Y-m')}.xlsx";
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsFile);

        if ($email) {
            $sender = $this->mailer->getTransport() instanceof \Swift_SmtpTransport ?
                $this->mailer->getTransport()->getUsername() : "report@costlocker.com";
            $email = Swift_Message::newInstance()
                ->addTo($email)
                ->setFrom([$sender => 'Costlocker Reporting'])
                ->setSubject("Report {$month->format('Y-m')}")
                ->setBody("Report {$month->format('Y-m')}", 'text/plain')
                ->attach(Swift_Attachment::fromPath($xlsFile));

            $wasSent = $this->mailer->send($email);
            if ($wasSent) {
                unlink($xlsFile);
            } else {
                $output->writeln('<error>E-mail was not sent!</error>');
            }
        }
    }

    private function indexToLetter($number)
    {
        return chr(substr("000" . ($number + 65), -3));
    }
}
