<?php

namespace ByJG\PHPThread;

use ByJG\Cache\Psr16\TmpfsCacheEngine;

class SharedMemory
{
    protected static ?TmpfsCacheEngine $memory = null;

    public static function getInstance(): TmpfsCacheEngine
    {
        if (empty(self::$memory)) {
            self::$memory = new TmpfsCacheEngine(prefix: bin2hex(random_bytes(8)));
        }
        return self::$memory;
    }
}