<?php

namespace App\Helpers;

use RuntimeException;

class ContainerMetadata
{
    public function __construct()
    {
    }

    public function generateContainerMetaData(string $hash): ?string
    {        
        $metaData  = '{'.PHP_EOL;
        $metaData .= '  "20819": {'.PHP_EOL;
        $metaData .= '    "hash": "'.$hash.'"'.PHP_EOL;
        $metaData .= '  }'.PHP_EOL;
        $metaData .= '}'.PHP_EOL;
        
        return !empty($hash) ? $metaData : '';
    }

}