<?php

namespace Costlocker\Reports\Config;

class DatesTest extends \PHPUnit\Framework\TestCase
{
    /** @dataProvider provideMonth */
    public function testGetNextMonth($currentMonth, $expectedMonth)
    {
        $nextMonth = Dates::getNextMonth(new \DateTime($currentMonth));
        assertThat($nextMonth->format('Y-m'), is($expectedMonth));
    }

    public function provideMonth()
    {
        return [
            ['2016-02-01', '2016-03'],
            ['2016-02-28', '2016-03'],
            ['2016-02-29', '2016-03'],
            ['2016-12-31', '2017-01'],
        ];
    }

    public function provideDay()
    {
        return [
            ['2016-01-01', '2016-01-31 23:59:59'],
            ['2016-01-31 20:00', '2016-01-31 23:59:59'],
        ];
    }

    public function testOriginalDateIsNotChanged()
    {
        $currentMonth = new \DateTime('2017-01-01');
        Dates::getNextMonth($currentMonth);
        assertThat($currentMonth->format('Y-m'), is('2017-01'));
    }

    /** @dataProvider provideMonthsInterval */
    public function testGetMonthsBetweenTwoDates($monthStart, $monthEnd, $expectedMonthsCount)
    {
        $months = Dates::getMonthsBetween(new \DateTime($monthStart), new \DateTime($monthEnd));
        assertThat($months, is(arrayWithSize($expectedMonthsCount)));
    }

    public function provideMonthsInterval()
    {
        return [
            ['previous month', 'previous month', 1],
            // "last month" does not always work without "first day of"
            // https://gist.github.com/costlockerbot/725df07c37b38e47789c602175d6d65e
            ['first day of previous month', 'first day of this month', 2],
            ['first day of last month', 'first day of this month', 2],
            ['2016-01-01', '2016-12-31', 12],
            ['this month', 'previous month', 0],
        ];
    }

    public function testGetFullMonth()
    {
        $months = Dates::getMonthsBetween(new \DateTime('2019-06-10'), new \DateTime('2019-06-10'));
        assertThat($months, is(arrayWithSize(1)));
        assertThat($months[0]->format('Y-m-d'), is('2019-06-01'));
    }

    /** @dataProvider provideWeek */
    public function testGetWeek($dayInWeek, $expectedWeek, $expectedStart, $expectedEnd)
    {
        list($start, $end, $week) = Dates::getWeek(new \DateTime($dayInWeek));
        assertThat($week, is($expectedWeek));
        assertThat($start->format('Y-m-d'), is($expectedStart));
        assertThat($end->format('Y-m-d'), is($expectedEnd));
    }

    public function provideWeek()
    {
        return [
            ['2019-04-22', 17, '2019-04-22', '2019-04-28'],
            ['2019-04-24', 17, '2019-04-22', '2019-04-28'],
            ['2019-04-28', 17, '2019-04-22', '2019-04-28'],
            ['2019-04-29', 18, '2019-04-29', '2019-05-05'],
        ];
    }
}
