<?php

namespace Costlocker\Reports;

interface GenerateReportPresenter
{
    public function startExtracting(array $runtimeConfig);

    public function finishExtracting(\DateTime $date);

    public function startTransforming();

    public function startLoading();

    public function finish(array $exports);

    public function error($message, $detail);
}
