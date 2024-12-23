<?php

namespace ByJG\PHPThread\Handler;

use ByJG\PHPThread\ThreadStatus;
use parallel\Runtime;

class ParallelHandler implements ThreadInterface
{
    protected \Closure $closure;
    protected Runtime $runtime;
    protected $future = null;

    public function start(): void
    {
        $fnArgs = func_get_args();

        $this->runtime = new Runtime();
        $this->future = $this->runtime->run($this->closure, $fnArgs);
    }

    public function getResult(): mixed
    {
        return $this->future->value();
    }

    public function terminate(int $signal = SIGKILL, bool $wait = false)
    {
        $this->runtime->kill();
    }

    public function isRunning(): bool
    {
        return !$this->future->cancelled() && !$this->future->done();
    }

    public function setClosure(\Closure $closure): void
    {
        $this->closure = $closure;
    }

    public function join(): void
    {
        while (!$this->future->cancelled() && !$this->future->done()) {
            usleep(50000);
        }
    }

    public function getClassName(): string
    {
        return ParallelHandler::class;
    }

    public function getStatus(): ThreadStatus
    {
        if (empty($this->future)) {
            return ThreadStatus::notStarted;
        } else if ($this->future->cancelled()) {
            return ThreadStatus::error;
        } else if ($this->future->done()) {
            return ThreadStatus::finished;
        } else {
            return ThreadStatus::running;
        }
    }
}