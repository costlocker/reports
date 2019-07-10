<?php

namespace Costlocker\Reports\Transform;

use Costlocker\Reports\ReportSettings;

interface Transformer
{
    public function __invoke(array $reports, ReportSettings $settings);
}
