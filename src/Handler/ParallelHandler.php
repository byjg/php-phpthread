<?php

namespace ByJG\PHPThread\Handler;

class ParallelHandler implements ThreadInterface
{
    protected $closure;
    protected $runtime;
    protected $future;

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
            sleep(1);
        }
    }

    public function getClassName()
    {
        return ParallelHandler::class;
    }
}