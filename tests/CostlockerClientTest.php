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

    private function whenApiReturns($response)
    {
        $this->httpClient->shouldReceive('get')->andReturn(new Response(200, [], file_get_contents(__DIR__ . "/fixtures/{$response}")));
    }
}
