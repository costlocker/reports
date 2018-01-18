<?php

return array(
    // http://swiftmailer.org/docs/sending.html - default mail()
    'mailer' => \Swift_MailTransport::newInstance(),
    'customReports' => function (\PhpOffice\PhpSpreadsheet\Spreadsheet $s) {
        return [];
    },
);
