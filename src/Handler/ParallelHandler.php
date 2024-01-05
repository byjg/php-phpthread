<?php

namespace ByJG\PHPThread\Handler;

use ByJG\PHPThread\Thread;

class ParallelHandler implements ThreadInterface
{
    protected $closure;
    protected $runtime;
    protected $future = null;

    public function execute()
    {
        $fnArgs = func_get_args();

        $this->runtime = new \parallel\Runtime();
        $this->future = $this->runtime->run($this->closure, $fnArgs);
    }

    public function getResult()
    {
        return $this->future->value();
    }

    public function stop($signal = SIGKILL, $wait = false)
    {
        $this->runtime->kill();
    }

    public function isAlive()
    {
        return !$this->future->cancelled() && !$this->future->done();
    }

    public function setClosure(\Closure $closure)
    {
        $this->closure = $closure;
    }

    public function waitFinish()
    {
        while (!$this->future->cancelled() && !$this->future->done()) {
            usleep(50000);
        }
    }

    public function getClassName()
    {
        return ParallelHandler::class;
    }

    public function getStatus()
    {
        if (empty($this->future)) {
            return Thread::STATUS_NOT_STARTED;
        } else if ($this->future->cancelled()) {
            return Thread::STATUS_ERROR;
        } else if ($this->future->done()) {
            return Thread::STATUS_FINISHED;
        } else {
            return Thread::STATUS_RUNNING;
        }
    }
}