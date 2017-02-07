<?php

namespace Costlocker\Reports\Inspiro;

class InspiroProviderTest extends \PHPUnit_Framework_TestCase
{
    /** @dataProvider provideDates */
    public function testCompareDatesLikeInCostlockerFilters(array $project, $expectedState)
    {
        $provider = new InspiroProvider();
        assertThat(
            $provider->determineProjectState(
                \DateTime::createFromFormat('Y-m-d', '2017-01-31'),
                $project
            ),
            is($expectedState)
        );
    }

    public function provideDates()
    {
        return [
            'project is running if da_start is in date range' => [
                [
                    'state' => 'running',
                    'da_start' => '2017-01-01',
                    'da_end' => '2017-03-01',
                ],
                'running'
            ],
            'project is finished if da_end is in date range' => [
                [
                    'state' => 'finished',
                    'da_start' => '2017-01-01',
                    'da_end' => '2017-01-02',
                ],
                'finished'
            ],
            'ignore running projects from another year' => [
                [
                    'state' => 'running',
                    'da_start' => '2016-01-01',
                    'da_end' => '2016-03-01',
                ],
                'legacy'
            ],
            'ignore finished projects from another year' => [
                [
                    'state' => 'finished',
                    'da_start' => '2016-01-01',
                    'da_end' => '2016-01-02',
                ],
                'legacy'
            ],
        ];
    }
}
