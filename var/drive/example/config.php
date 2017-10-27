<?php

use Costlocker\Reports\ReportSettings;

return [
    'profitability' => [
        'folders' => ['folderId'],
        'title' => function (ReportSettings $settings) {
            $company = $settings->company ? "{$settings->company} - " : '';
            $title = $settings->filter ?: 'Firma';
            return "{$company}{$title} {$settings->yearStart}";
        }
    ],
];
