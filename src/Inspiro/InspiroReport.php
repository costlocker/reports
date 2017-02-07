<?php

namespace Costlocker\Reports\Inspiro;

class InspiroReport
{
    public $lastDay;
    public $clients;

    public function getActiveClients()
    {
        $activeClients =  array_filter(
            $this->clients,
            function (array $metrics) {
                return $metrics['running']['projects'] > 0 || $metrics['finished']['projects'] > 0;
            }
        );
        ksort($activeClients);
        return $activeClients;
    }
}
