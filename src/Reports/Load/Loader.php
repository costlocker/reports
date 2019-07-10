<?php

namespace Costlocker\Reports\Load;

interface Loader
{
    /**
     * Returns
     * - null if not configured
     * - false if error
     * - everything else if OK
     */
    public function __invoke($filePath, $title, $config);
}
