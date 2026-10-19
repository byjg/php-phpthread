<?php

namespace ByJG\PHPThread\Handler;

use ByJG\PHPThread\ThreadStatus;
use Closure;
use Throwable;

/**
 * Fallback handler used when neither the parallel extension nor pcntl_fork can be used
 * (e.g. PHP-FPM or any other non-CLI SAPI).
 *
 * The closure runs synchronously in the current process when start() is called,
 * so there is no parallelism.
 */
class SyncHandler implements ThreadInterface
{
    private Closure $closure;
    private bool $started = false;
    private mixed $threadResult = null;

    private ?Closure $onFinish = null;

    public function __construct(?Closure $onFinish = null)
    {
        $this->onFinish = $onFinish;
    }

    #[\Override]
    public function setClosure(Closure $closure): void
    {
        $this->closure = $closure;
    }

    /**
     * Run the closure inline and keep its result (or the exception it threw)
     */
    #[\Override]
    public function start(mixed ...$args): void
    {
        $this->started = true;

        try {
            $this->threadResult = call_user_func($this->closure, ...$args);
        } catch (Throwable $t) {
            $this->threadResult = $t;
        }

        if (!empty($this->onFinish)) {
            call_user_func($this->onFinish);
        }
    }

    /**
     * @return mixed
     * @throws Throwable The exception thrown by the closure, re-thrown on every call
     */
    #[\Override]
    public function getResult(): mixed
    {
        if ($this->threadResult instanceof Throwable) {
            throw $this->threadResult;
        }

        return $this->threadResult;
    }

    /**
     * Nothing to stop: the closure already finished when start() returned
     */
    #[\Override]
    public function terminate(int $signal = SIGKILL, bool $wait = false): void
    {
    }

    #[\Override]
    public function isRunning(): bool
    {
        return false;
    }

    /**
     * Nothing to wait for: the closure already finished when start() returned
     */
    #[\Override]
    public function join(): void
    {
    }

    #[\Override]
    public function getClassName(): string
    {
        return SyncHandler::class;
    }

    #[\Override]
    public function getStatus(): ThreadStatus
    {
        return $this->started ? ThreadStatus::Finished : ThreadStatus::NotStarted;
    }
}
