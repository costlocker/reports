<?php

namespace Costlocker\Reports;

use Mockery as m;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class CostlockerClientTest extends \PHPUnit_Framework_TestCase
{
    private $httpClient;
    private $costlocker;

    protected function setUp()
    {
        $this->httpClient = m::mock(Client::class);
        $this->costlocker = new CostlockerClient($this->httpClient);
    }

    public function testLoadProjectAndClients()
    {
        $this->whenApiReturns('projects-and-clients.json');
        $this->assertEquals(
            [
                1 => [
                    'name' => 'eshop',
                    'client' => 'Kamil',
                ],
                33 => [
                    'name' => 'comm strategy',
                    'client' => 'deactivated',
                ],
            ],
            $this->costlocker->projects()
        );
    }

    public function testGroupPeopleByProject()
    {
        $this->whenApiReturns('people-and-costs.json');
        $this->assertEquals(
            [
                1 => [
                    'name' => 'Own TROK EZ5H Test',
                    'salary_hours' => 160,
                    'salary_amount' => 20000,
                    'projects' => [
                        33 => [
                            'client_rate' => 20,
                            'hrs_budget' => 10 + 2,
                            'hrs_tracked' => 1.0858333333333401,
                        ],
                        1 => [
                            'client_rate' => 800,
                            'hrs_budget' => 70 + 1,
                            'hrs_tracked' => 2.611944444444422,
                        ],
                    ],
                ],
                2 => [
                    'name' => 'Uncle Bob',
                    'salary_hours' => 160,
                    'salary_amount' => 20000,
                    'projects' => [
                        33 => [
                            'client_rate' => 50,
                            'hrs_budget' => 10 + 20,
                            'hrs_tracked' => 0,
                        ],
                        1 => [
                            'client_rate' => 500,
                            'hrs_budget' => 100,
                            'hrs_tracked' => 71.95,
                        ],
                    ],
                ],
            ],
            $this->costlocker->people()
        );
    }

    private function whenApiReturns($response)
    {
        $this->httpClient->shouldReceive('get')->andReturn(new Response(200, [], file_get_contents(__DIR__ . "/fixtures/{$response}")));
    }
}
