<?php

namespace Costlocker\Reports\Load;

use Swift_Mailer;
use Swift_Message;
use Swift_Attachment;

class Mailer implements Loader
{
    private $mailer;
    private $sender;

    public function __construct(Swift_Mailer $m, $sender)
    {
        $this->mailer = $m;
        $this->sender = $sender;
    }

    public function __invoke($xlsFile, $title, $recipient)
    {
        if (!$recipient || !$this->sender) {
            return false;
        }
        try {
            $email = (new Swift_Message())
                ->addTo($recipient)
                ->setFrom([$this->sender => 'Costlocker Reporter'])
                ->setSubject($title)
                ->setBody($title, 'text/plain')
                ->attach(Swift_Attachment::fromPath($xlsFile));
            return $this->mailer->send($email) ? true : false;
        } finally {
            $this->mailer->getTransport()->stop();
        }
    }
}
