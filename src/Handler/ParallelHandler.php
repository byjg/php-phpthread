<?php

namespace ByJG\PHPThread\Handler;

use ByJG\PHPThread\ThreadStatus;
use parallel\Runtime;
use RuntimeException;

/** @psalm-suppress UndefinedClass */
class ParallelHandler implements ThreadInterface
{
    protected \Closure $closure;
    protected Runtime $runtime;
    protected $future = null;

    public function __construct()
    {
        if (php_sapi_name() != 'cli') {
            throw new RuntimeException('Threads only works in CLI mode');
        }
    }

    public function start(mixed ...$args): void
    {
        $this->runtime = new Runtime();
        $this->future = $this->runtime->run($this->closure, $args);
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
            usleep(100);
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