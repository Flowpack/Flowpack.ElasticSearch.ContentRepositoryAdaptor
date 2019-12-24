<?php
declare(strict_types=1);

namespace Flowpack\ElasticSearch\ContentRepositoryAdaptor;

/*
 *  (c) 2019 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

class EsProfiler
{
    protected static $time = 0;

    protected static $nodes = 0;

    public static function add(int $data, string $type = 'time'): void
    {
        self::$$type += $data;
    }

    public static function get( string $type = 'time'): int
    {
        return self::$$type;
    }

    public static function getAndReset( string $type = 'time'): int
    {
        $$type = self::$$type;
        self::$$type = 0;
        return $$type;
    }
}
