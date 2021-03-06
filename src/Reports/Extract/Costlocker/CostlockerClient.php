<?php

namespace Costlocker\Reports\Extract\Costlocker;

interface CostlockerClient
{
    public function request(array $request);

    public function restApi($endpoint);

    public function map(array $rawData, $id);

    public function sum(array $rawData, $attribute);
}
