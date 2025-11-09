<?php

namespace ByJG\PHPThread\Handler;

use ByJG\PHPThread\ThreadStatus;
use parallel\Runtime;

/** @psalm-suppress UndefinedClass */
class ParallelHandler implements ThreadInterface
{
    protected \Closure $closure;
    protected Runtime $runtime;
    protected $future = null;

    #[\Override]
    public function start(mixed ...$args): void
    {
        $this->runtime = new Runtime();
        $this->future = $this->runtime->run($this->closure, $args);
    }

    #[\Override]
    public function getResult(): mixed
    {
        return $this->future->value();
    }

    #[\Override]
    public function terminate(int $signal = SIGKILL, bool $wait = false)
    {
        $this->runtime->kill();
    }

    #[\Override]
    public function isRunning(): bool
    {
        return !$this->future->cancelled() && !$this->future->done();
    }

    #[\Override]
    public function setClosure(\Closure $closure): void
    {
        $this->closure = $closure;
    }

    #[\Override]
    public function join(): void
    {
        while (!$this->future->cancelled() && !$this->future->done()) {
            usleep(100);
        }
    }

    #[\Override]
    public function getClassName(): string
    {
        return ParallelHandler::class;
    }

    #[\Override]
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