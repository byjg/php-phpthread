<?php

namespace ByJG\PHPThread;

use ByJG\Cache\Psr16\TmpfsCacheEngine;

class SharedMemory
{
    protected static ?TmpfsCacheEngine $memory = null;
    protected static ?string $prefix = null;

    public static function getInstance(): TmpfsCacheEngine
    {
        if (empty(self::$memory)) {
            self::$memory = new TmpfsCacheEngine(prefix: self::getPrefix());
        }
        return self::$memory;
    }

    /**
     * The prefix of the shared memory keys of this process
     *
     * @internal Used by ProcessHandler
     */
    public static function getPrefix(): string
    {
        if (self::$prefix === null) {
            self::$prefix = bin2hex(random_bytes(8));
        }
        return self::$prefix;
    }

    /**
     * Share the memory of another process. ProcessHandler uses it in the child process,
     * which does not inherit the parent memory as a fork does.
     *
     * @internal Used by ProcessHandler
     */
    public static function usePrefix(string $prefix): void
    {
        self::$prefix = $prefix;
        self::$memory = null;
    }
}
