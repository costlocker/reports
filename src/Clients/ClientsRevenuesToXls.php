<?php

namespace Costlocker\Reports\Clients;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Costlocker\Reports\ReportSettings;
use Costlocker\Reports\XlsBuilder;

class ClientsRevenuesToXls
{
    private $spreadsheet;

    public function __construct(Spreadsheet $spreadsheet)
    {
        $this->spreadsheet = $spreadsheet;
    }

    public function __invoke(ClientsRevenuesReport $report, ReportSettings $settings)
    {
        $currencyFormat = XlsBuilder::getCurrencyFormat($settings->currency);

        $worksheet = new XlsBuilder($this->spreadsheet, $report->lastDay->format('Y'));
        $worksheet->addHeaders([
            ['Client', 'd6dce5'],
            ['Projects (finished projects)', 'ffd966', 'count'],
            ['Revenue (finished projects)', 'ffd966', $settings->currency],
            ['Revenue - Project Expenses (finished projects)', 'ffd966', $settings->currency],
            ['Profit (finished projects)', 'ffd966', $settings->currency],
            ['Projects (running projects)', 'fbe5d6', 'count'],
            ['Revenue (running projects)', 'fbe5d6', $settings->currency],
            ['Revenue - Project Expenses (running projects)', 'fbe5d6', $settings->currency],
            ['Profit (running projects)', 'fbe5d6', $settings->currency],
        ]);

        foreach ($report->getActiveClients() as $client => $billing) {
            $clientRowId = $worksheet->getRowId();
            $worksheet
                ->addRow(
                    [
                        $client,
                        [$billing['finished']['projects'], NumberFormat::FORMAT_NUMBER],
                        [$billing['finished']['revenue'], $currencyFormat],
                        ["=C{$clientRowId}-{$billing['finished']['expenses']}", $currencyFormat],
                        ['', $currencyFormat],
                        [$billing['running']['projects'], NumberFormat::FORMAT_NUMBER],
                        [$billing['running']['revenue'], $currencyFormat],
                        ["=G{$clientRowId}-{$billing['running']['expenses']}", $currencyFormat],
                        ['', $currencyFormat],
                    ],
                    'transparent'
                );
        }
    }
}
