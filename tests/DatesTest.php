<?php

namespace Costlocker\Reports;

class DatesTest extends \PHPUnit_Framework_TestCase
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
            ['previous month', 'this month', 2],
            ['2016-01-01', '2016-12-31', 12],
            ['this month', 'previous month', 0],
        ];
    }
}