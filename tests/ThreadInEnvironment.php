<?php

namespace Tests;

use ByJG\PHPThread\Thread;

/**
 * Thread with the environment checks replaced by fixed values
 */
class ThreadInEnvironment extends Thread
{
    public static bool $cli = true;
    public static bool $parallel = false;
    public static bool $fork = true;

    #[\Override]
    protected static function isCli(): bool
    {
        return self::$cli;
    }

    #[\Override]
    protected static function hasParallel(): bool
    {
        return self::$parallel;
    }

    #[\Override]
    protected static function hasFork(): bool
    {
        return self::$fork;
    }
}
