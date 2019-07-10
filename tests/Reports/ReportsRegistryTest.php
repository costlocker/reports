<?php

namespace Costlocker\Reports;

class ReportsRegistryTest extends \PHPUnit\Framework\TestCase
{
    public function testAutoload()
    {
        $registry = ReportsRegistry::autoload();
        assertThat($registry->getAvailableTypes(), arrayWithSize(atLeast(4)));
    }
}
