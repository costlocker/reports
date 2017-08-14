<?php

use Costlocker\Reports\ReportSettings;

return [
    'profitability' => [
        'folders' => ['folderId'],
        'title' => function (ReportSettings $settings) {
            $title = $settings->filter ?: 'Company';
            return "{$title} {$settings->yearStart}";
        }
    ],
];
