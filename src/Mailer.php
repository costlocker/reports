<?php

namespace Costlocker\Reports;

use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;

class Mailer
{
    private $mailer;

    public function __construct(Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function __invoke($recipient, $xlsFile, \DateTime $selectedMonth)
    {
        $email = Swift_Message::newInstance()
            ->addTo($recipient)
            ->setFrom(['do-not-reply@costlocker.com' => 'Costlocker Reporter'])
            ->setSubject("Report {$selectedMonth->format('Y-m')}")
            ->setBody("Report {$selectedMonth->format('Y-m')}", 'text/plain')
            ->attach(Swift_Attachment::fromPath($xlsFile));

        return $this->mailer->send($email);
    }
}
