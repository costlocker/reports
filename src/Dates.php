<?php

namespace Costlocker\Reports;

class Dates
{
    public static function getMonthsBetween(\DateTime $monthStart, \DateTime $monthEnd)
    {
        $months = [];
        if ($monthStart <= $monthEnd) {
            $lastMonth = $monthStart;
            while ($lastMonth->format('Ym') <= $monthEnd->format('Ym')) {
                $months[] = $lastMonth;
                $lastMonth = Dates::getNextMonth($lastMonth);
            }
        }
        return $months;
    }

    public static function getNextMonth(\DateTime $month)
    {
        $nextMonth = clone $month;
        $nextMonth->setDate($month->format('Y'), $month->format('m'), $month->format('t'));
        return $nextMonth->modify('+1 day');
    }
}
