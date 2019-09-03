<?php

namespace Costlocker\Reports\Config;

// Enums defined in schema.json
class Enum
{
    const FORMAT_XLS = 'xls';
    const FORMAT_HTML = 'html';

    const CURRENCY_CZK = 'CZK';
    const CURRENCY_EUR = 'EUR';

    const DATE_RANGE_WEEK = 'week';
    const DATE_RANGE_LAST_7_DAYS = 'last7days';
    const DATE_RANGE_MONTHS = 'months';
    const DATE_RANGE_YEAR = 'year';
    const DATE_RANGE_ALLTIME = 'alltime';

    public static function translateDateRange($dateRange)
    {
        static $map = [
            Enum::DATE_RANGE_ALLTIME => 'All-time report',
            Enum::DATE_RANGE_WEEK => 'Weekly report',
            Enum::DATE_RANGE_LAST_7_DAYS => 'Last 7 days',
            Enum::DATE_RANGE_MONTHS => 'Monthly report',
            Enum::DATE_RANGE_YEAR => 'Yearly report',
        ];
        return $map[$dateRange];
    }
}
