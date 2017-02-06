<?php

namespace Costlocker\Reports\Inspiro;

class InspiroProviderTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider provideDates */
    public function testDetermineProjectStatus($year, $dateStart, $dateEnd, $expectedState)
    {
        $provider = new InspiroProvider();
        assertThat(
            $provider->determineProjectState(
                \DateTime::createFromFormat('Y-m-d', $year),
                $dateStart,
                $dateEnd
            ),
            is($expectedState)
        );
    }

    public function provideDates()
    {
        return [
            ['2017-01-31', '2017-01-01', null, 'running'],
            ['2017-01-31', '2017-01-01', '2017-03-01', 'running'],
            ['2017-01-31', '2017-01-01', '2017-01-02', 'finished'],
            ['2017-01-31', '2016-01-01', null, 'legacy'],
            ['2017-01-31', '2016-01-01', '2016-12-31', 'legacy'],
        ];
    }
}
