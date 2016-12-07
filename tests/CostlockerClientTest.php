<?php

namespace Costlocker\Reports;

class CostlockerClientTest extends \PHPUnit_Framework_TestCase
{
    public function testComposerAutoloader()
    {
        assertThat(class_exists(CostlockerClient::class), is(true));
    }
}
