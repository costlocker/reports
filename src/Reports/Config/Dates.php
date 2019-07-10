<?php

namespace Costlocker\Reports\Config;

class Dates
{
    public static function getMonthsBetween(\DateTime $monthStart, \DateTime $monthEnd)
    {
        $months = [];
        if ($monthStart <= $monthEnd) {
            $lastMonth = \DateTime::createFromFormat('Y-m-d', $monthStart->format('Y-m-01'));
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

    public static function getWeek(\DateTime $dayInWeek)
    {
        $dayNumber = $dayInWeek->format('N');
        $monday = clone $dayInWeek;
        $sunday = clone $dayInWeek;
        $monday->modify('-' . ($dayNumber - 1) . ' days');
        $sunday->modify('+' . (7 - $dayNumber) . ' days');
        return [
            $monday,
            $sunday,
            $dayInWeek->format('W'),
        ];
    }
}
