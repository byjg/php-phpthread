<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ForkHandler;
use ByJG\PHPThread\Handler\PThreadHandler;
use ByJG\PHPThread\Handler\ThreadInterface;
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
     * @param mixed $callable string with the function name or a array with the instance and the method name
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function __construct(callable $callable)
    {
        $this->setCallable($callable);
    }

    /**
     * @return ThreadInterface
     */
    public function getThreadInstance()
    {
        if (!is_null($this->threadInstance)) {
            return $this->threadInstance;
        }

        if (class_exists('\Thread', true)) {
            $this->threadInstance = new PThreadHandler();
        } elseif (function_exists('pcntl_fork')) {
            $this->threadInstance = new ForkHandler();
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
     * @param callable $callable
     * @return mixed
     */
    public function setCallable(callable $callable)
    {
        $this->getThreadInstance()->setCallable($callable);
    }
}
