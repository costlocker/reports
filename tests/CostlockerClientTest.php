<?php

namespace Costlocker\Reports;

use Mockery as m;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Costlocker\Reports\Client\HttpClient;

class CostlockerClientTest extends \PHPUnit_Framework_TestCase
{
    private $httpClient;
    private $costlockerClient;
    private $profitability;

    protected function setUp()
    {
        $this->httpClient = m::mock(Client::class);
        $this->costlockerClient = new HttpClient($this->httpClient);
        $this->profitability = new Profitability\ProfitabilityProvider($this->costlockerClient, 20000);
    }

    public function testLoadProjectAndClients()
    {
        $this->whenApiReturns('projects-and-clients-and-tags.json');
        $this->assertEquals(
            [
                1 => [
                    'name' => 'eshop',
                    'client' => 'Kamil',
                    'tags' => ['billable', 'design'],
                ],
                33 => [
                    'name' => 'comm strategy',
                    'client' => 'deactivated',
                    'tags' => [],
                ],
            ],
            $this->profitability->projects()
        );
    }

    public function testGroupPeopleByProject()
    {
        $this->whenApiReturns('people.json', 'timesheet-february.json', 'timesheet-march.json', 'personnel-costs.json');
        $this->assertEquals(
            [
                1 => [
                    'name' => 'Own TROK EZ5H Test',
                    'is_employee' => true,
                    'salary_hours' => 160,
                    'salary_amount' => 20000,
                    'hourly_rate' => 125,
                    'projects' => [
                        33 => [
                            'client_rate' => 20,
                            'hrs_budget' => 10 + 2,
                            'hrs_tracked_total' => 1.0858333333333401,
                            'hrs_tracked_month' => 1800 / 3600,
                            'hrs_tracked_after_month' => 0,
                        ],
                        1 => [
                            'client_rate' => 800,
                            'hrs_budget' => 70 + 1,
                            'hrs_tracked_total' => 2.611944444444422,
                            'hrs_tracked_month' => (9823 + 2000) / 3600,
                            'hrs_tracked_after_month' => 6423 / 3600,
                        ],
                    ],
                ],
                2 => [
                    'name' => 'Uncle Bob',
                    'is_employee' => true,
                    'salary_hours' => 80,
                    'salary_amount' => 30000,
                    'hourly_rate' => 125,
                    'projects' => [
                        33 => [
                            'client_rate' => 50,
                            'hrs_budget' => 10 + 20,
                            'hrs_tracked_total' => 0,
                            'hrs_tracked_month' => 0,
                            'hrs_tracked_after_month' => 0,
                        ],
                        1 => [
                            'client_rate' => 500,
                            'hrs_budget' => 100,
                            'hrs_tracked_total' => 71.95,
                            'hrs_tracked_month' => 1823 / 3600,
                            'hrs_tracked_after_month' => 0,
                        ],
                    ],
                ],
                3 => [
                    'name' => 'Lonely Wolf',
                    'is_employee' => false,
                    'salary_hours' => 0,
                    'salary_amount' => 0 * 100,
                    'hourly_rate' => 100,
                    'projects' => [],
                ],
            ],
            $this->profitability->people(new \DateTime('2015-02-01'))
        );
    }

    private function whenApiReturns()
    {
        foreach (func_get_args() as $response) {
            $this->httpClient->shouldReceive('get')->once()->andReturn(
                new Response(200, [], file_get_contents(__DIR__ . "/fixtures/{$response}"))
            );
        }
    }
}
