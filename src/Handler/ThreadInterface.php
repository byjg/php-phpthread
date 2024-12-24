<?php

namespace ByJG\PHPThread\Handler;

use ByJG\PHPThread\ThreadStatus;
use Closure;
use RuntimeException;

interface ThreadInterface
{
    /**
     * Start the thread
     *
     * @param mixed ...$args
     * @throws RuntimeException
     */
    public function start(mixed ...$args): void;

    /**
     * Get the thread result
     *
     * @return mixed
     */
    public function getResult(): mixed;

    /**
     * Stop or terminate the thread.
     *
     * @param int $signal Signal to send for stopping the thread. Default is SIGKILL.
     * @param bool $wait Whether to wait for the thread to terminate. Default is false.
     */
    public function terminate(int $signal = SIGKILL, bool $wait = false);

    /**
     * Check if the thread is still running.
     *
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Set the thread Closure method
     * @param Closure $closure
     * @return void
     */
    public function setClosure(Closure $closure): void;

    /**
     * Wait for the thread finish and join to main thread;
     *
     * @return void
     */
    public function join(): void;

    /**
     * Return the thread class name
     * @return string
     */
    public function getClassName(): string;

    public function getStatus(): ThreadStatus;
}
