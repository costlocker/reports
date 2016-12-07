<?php

namespace Costlocker\Reports;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use GuzzleHttp\Client;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet;
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

        $table = new Table($output);
        $table->setHeaders([
            'Person',
            'Project',
            'Client',
            'Hours',
            'Salary',
            'Tracked Hours',
            'Estimated Hours',
            'Billable',
            'Billable',
            'Client Rate',
        ]);
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
                    $project['client_rate'],
                ]);
            }
        }
        foreach ($projects as $project) {
            $table->addRow([
                $project['name'],
                $project['client'],
            ]);
        }
        $table->render();

        $output->writeln(json_encode($timesheet, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle($month->format('Y-m'));

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save("var/reports/{$month->format('Y-m')}.xlsx");
    }
}
