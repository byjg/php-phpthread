<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ForkHandler;
use ByJG\PHPThread\Handler\ParallelHandler;
use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Native Implementation of Threads in PHP.
 *
 * A class to spawn a thread. Only works in *nix environments,
 * as Windows platform is missing libpcntl.
 *
 * Forks the process.
 */
class Thread
{
    const STATUS_NOT_STARTED = 'not-started';

    const STATUS_RUNNING = 'running';
    const STATUS_FINISHED = 'finished';
    const STATUS_ERROR = 'error';


    /**
     * @return ThreadInterface
     */
    public static function create(\Closure $closure)
    {
        if (class_exists('\parallel\Runtime', true)) {
            $instance = new ParallelHandler();
        } elseif (function_exists('pcntl_fork')) {
            $instance = new ForkHandler();
        } else {
            throw new RuntimeException(
                'PHP need to be compiled with ZTS extension or compiled with the --enable-pcntl. ' .
                'Windows is not supported.'
            );
        }

        $instance->setClosure($closure);

        return $instance;
    }

}
