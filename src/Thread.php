<?php

namespace ByJG\PHPThread;

use ByJG\Cache\Exception\InvalidArgumentException;
use ByJG\PHPThread\Handler\ForkHandler;
use ByJG\PHPThread\Handler\ParallelHandler;
use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
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
    /**
     * @param Closure $closure
     * @param Closure|null $onFinish
     * @return ThreadInterface
     */
    public static function create(Closure $closure, ?Closure $onFinish = null): ThreadInterface
    {
        if (class_exists('\parallel\Runtime', true)) {
            $instance = new ParallelHandler();
        } elseif (function_exists('pcntl_fork')) {
            $instance = new ForkHandler($onFinish);
        } else {
            throw new RuntimeException(
                'PHP need to be compiled with ZTS extension or compiled with the --enable-pcntl. ' .
                'Windows is not supported.'
            );
        }

        $instance->setClosure($closure);

        return $instance;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     */
    public static function gc(): void
    {
        SharedMemory::getInstance()->clear();
    }

}
