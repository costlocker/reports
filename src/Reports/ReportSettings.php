<?php

namespace Costlocker\Reports;

class ReportSettings
{
    /** @var \DateTime|null - temporarily accessible only in Extractor */
    public $date;
    /** @var string - temporarily accessible only in Transformer */
    public $title;

    public $currency;
    public $customConfig = [];

    /** @var Extract\Costlocker\CostlockerUrlGenerator */
    public $costlocker;
}
