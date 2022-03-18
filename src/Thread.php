<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ForkHandler;
use ByJG\PHPThread\Handler\ParallelHandler;
use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;
use InvalidArgumentException;
use RuntimeException;

/**
 * Native Implementation of Threads in PHP.
 *
 * A class to spawn a thread. Only works in *nix environments,
 * as Windows platform is missing libpcntl.
 *
 * Forks the process.
 */
class Thread implements ThreadInterface
{
    /**
     * @var ThreadInterface
     */
    private $threadInstance = null;

    /**
     * constructor method
     *
     * @param Closure $closure
     */
    public function __construct(Closure $closure)
    {
        $this->setClosure($closure);
    }

    private $threadHandlerArguments = [];

    /**
     * @param array $arguments
     */
    public function setThreadHandlerArguments($arguments)
    {
        $this->threadHandlerArguments = $arguments;
    }

    private function getThreadHandlerArguments($property)
    {
        return isset($this->threadHandlerArguments[$property]) ? $this->threadHandlerArguments[$property] : null;
    }

    /**
     * @return ThreadInterface
     */
    public function getThreadInstance()
    {
        if (!is_null($this->threadInstance)) {
            return $this->threadInstance;
        }

        if (class_exists('\parallel\Runtime', true)) {
            $this->threadInstance = new ParallelHandler();
        } elseif (function_exists('pcntl_fork')) {
            $this->threadInstance = new ForkHandler(
                $this->getThreadHandlerArguments('max-size'),
                $this->getThreadHandlerArguments('default-permission')
            );
        } else {
            throw new RuntimeException(
                'PHP need to be compiled with ZTS extension or compiled with the --enable-pcntl. ' .
                'Windows is not supported.'
            );
        }

        return $this->threadInstance;
    }


    /**
     * Start the thread
     *
     * @throws RuntimeException
     */
    public function execute()
    {
        $args = func_get_args();
        call_user_func_array([$this->getThreadInstance(), 'execute'], $args);
    }

    /**
     * Get the thread result from the shared memory block and erase it
     *
     * @return mixed
     * @throws \Throwable
     */
    public function getResult()
    {
        return $this->getThreadInstance()->getResult();
    }

    /**
     * Kill a thread
     *
     * @param int $signal
     * @param bool $wait
     */
    public function stop($signal = SIGKILL, $wait = false)
    {
        return $this->getThreadInstance()->stop($signal, $wait);
    }

    /**
     * Check if the forked process is alive
     * @return bool
     */
    public function isAlive()
    {
        return $this->getThreadInstance()->isAlive();
    }

    public function waitFinish()
    {
        $this->getThreadInstance()->waitFinish();
    }

    /**
     * Set the thread callable method
     * @param Closure $closure
     * @return mixed
     */
    public function setClosure(Closure $closure)
    {
        $this->getThreadInstance()->setClosure($closure);
    }

    public function getClassName()
    {
        return $this->getThreadInstance()->getClassName();
    }
}
