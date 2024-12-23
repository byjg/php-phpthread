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
     * @throws RuntimeException
     */
    public function execute(): void;

    /**
     * Get the thread result
     *
     * @return mixed
     */
    public function getResult(): mixed;

    /**
     * Kill a thread
     *
     * @param int $signal
     * @param bool $wait
     */
    public function stop(int $signal = SIGKILL, bool $wait = false);

    /**
     * Checkif the thread is not Terminated
     *
     * @return bool
     */
    public function isAlive(): bool;

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
    public function waitFinish(): void;

    /**
     * Return the thread class name
     * @return string
     */
    public function getClassName(): string;

    public function getStatus(): ThreadStatus;
}
