<?php

namespace Costlocker\Reports;

class Dates
{
    public static function getNextMonth(\DateTime $month)
    {
        $nextMonth = clone $month;
        $nextMonth->setDate($month->format('Y'), $month->format('m'), $month->format('t'));
        return $nextMonth->modify('+1 day');
    }
}
