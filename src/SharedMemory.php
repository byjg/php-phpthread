<?php

namespace ByJG\PHPThread;

use ByJG\Cache\Psr16\ShmopCacheEngine;

class SharedMemory
{
    protected static ShmopCacheEngine $shmopCacheEngine;

    protected function __construct($maxSharedMemorySize, $defaultPermission)
    {

    }

    public static function getInstance(): ShmopCacheEngine
    {
        self::$shmopCacheEngine = new ShmopCacheEngine(
            [
                'max-size' => 0x100000,
                'default-permission' => '0700'
            ]
        );

        return self::$shmopCacheEngine;
    }



}