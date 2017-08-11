<?php

namespace Costlocker\Reports;

interface CostlockerClient
{
    public function request(array $request);

    public function map(array $rawData, $id);

    public function sum(array $rawData, $attribute);
}
