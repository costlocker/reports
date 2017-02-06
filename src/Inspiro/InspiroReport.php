<?php

namespace Costlocker\Reports\Inspiro;

class InspiroReport
{
    public $lastDay;
    public $clients;

    public function getActiveClients()
    {
        return array_filter(
            $this->clients,
            function (array $metrics) {
                return $metrics['running']['projects'] > 0 || $metrics['finished']['projects'] > 0;
            }
        );
    }
}
