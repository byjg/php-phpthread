<?php

namespace ByJG\PHPThread;

use ByJG\Cache\Exception\InvalidArgumentException;
use ByJG\PHPThread\Handler\ForkHandler;
use ByJG\PHPThread\Handler\ParallelHandler;
use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Native Implementation of Threads in PHP.
 *
 * A class to spawn a thread. Uses the parallel extension (ZTS) or forks the process (pcntl).
 * Outside the CLI, or when neither is available, the closure runs synchronously
 * in the current process (no parallelism).
 */
class Thread
{
    /**
     * @var class-string<ThreadInterface>|null
     */
    protected static ?string $forcedHandler = null;

    /**
     * @param Closure $closure
     * @param Closure|null $onFinish
     * @return ThreadInterface
     */
    public static function create(Closure $closure, ?Closure $onFinish = null): ThreadInterface
    {
        $instance = match (static::handlerClass()) {
            ParallelHandler::class => new ParallelHandler(),
            ForkHandler::class => new ForkHandler($onFinish),
            default => new SyncHandler($onFinish),
        };

        $instance->setClosure($closure);

        return $instance;
    }

    /**
     * Force the handler create() uses, or restore the automatic choice with null.
     * A forced ForkHandler or ParallelHandler still throws when it cannot run.
     *
     * @param class-string<ThreadInterface>|null $handlerClass ParallelHandler, ForkHandler or SyncHandler
     */
    public static function useHandler(?string $handlerClass): void
    {
        if ($handlerClass !== null
            && !in_array($handlerClass, [ParallelHandler::class, ForkHandler::class, SyncHandler::class], true)
        ) {
            throw new \InvalidArgumentException(
                "Invalid handler '$handlerClass': use ParallelHandler, ForkHandler or SyncHandler"
            );
        }

        self::$forcedHandler = $handlerClass;
    }

    /**
     * The handler class create() uses: the one forced by useHandler() or, by default,
     * ParallelHandler or ForkHandler in the CLI and SyncHandler otherwise.
     *
     * @return class-string<ThreadInterface>
     */
    public static function handlerClass(): string
    {
        if (self::$forcedHandler !== null) {
            return self::$forcedHandler;
        }

        if (static::isCli()) {
            if (static::hasParallel()) {
                return ParallelHandler::class;
            }
            if (static::hasFork()) {
                return ForkHandler::class;
            }
        }

        return SyncHandler::class;
    }

    protected static function isCli(): bool
    {
        return php_sapi_name() === 'cli';
    }

    protected static function hasParallel(): bool
    {
        return class_exists('\parallel\Runtime', true);
    }

    protected static function hasFork(): bool
    {
        return function_exists('pcntl_fork');
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
