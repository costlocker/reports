<?php

namespace Costlocker\Reports\Extract;

use Costlocker\Reports\ReportSettings;
use Costlocker\Reports\Extract\Costlocker\CostlockerClient;

abstract class Extractor
{
    /** @var CostlockerClient */
    protected $client;

    public function __construct(CostlockerClient $c)
    {
        $this->client = $c;
    }

    abstract public static function getConfig(): ExtractorBuilder;

    abstract public function __invoke(ReportSettings $s): array;
}
