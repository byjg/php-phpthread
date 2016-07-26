<?php

namespace ByJG\PHPThread\Handler;

use ByJG\Cache\CacheContext;
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
class ForkHandler implements ThreadInterface
{
    protected $_threadKey;
    private $callable;
    private $_pid;

    /**
     * constructor method
     *
     * @throws RuntimeException
     * @throws InvalidArgumentException
     */
    public function __construct()
    {
        if (!function_exists('pcntl_fork')) {
            throw new RuntimeException('PHP was compiled without --enable-pcntl or you are running on Windows.');
        }

        /** Check if is configured */
        CacheContext::factory('phpthread');
    }

    /**
     * Private function for set the method will be forked;
     *
     * @param callable $callable string with the function name or a array with the instance and the method name
     * @return mixed|void
     */
    public function setCallable(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Start the thread
     *
     * @throws RuntimeException
     */
    public function execute()
    {
        $this->_threadKey = 'thread_' . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);

        if (($this->_pid = pcntl_fork()) == -1) {
            throw new RuntimeException('Couldn\'t fork the process');
        }

        if ($this->_pid) {
            // Parent
            //pcntl_wait($status); //Protect against Zombie children
        } else {
            // Child.
            pcntl_signal(SIGTERM, array($this, 'signalHandler'));
            $args = func_get_args();

            $callable = $this->callable;
            if (!is_string($callable)) {
                $callable = (array) $this->callable;
            }

            try {
                $return = call_user_func_array($callable, (array)$args);

                if (!is_null($return)) {
                    $this->saveResult($return);
                }
            } catch (\Exception $ex) {
                $this->saveResult($ex);
            }

            exit(0);
        }

        // Parent.
    }

    /**
     * Save the thread result in a shared memory block
     *
     * @param mixed $object Need to be serializable
     */
    protected function saveResult($object)
    {
        $cache = CacheContext::factory('phpthread');
        $cache->set($this->_threadKey, $object);
    }

    /**
     * Get the thread result from the shared memory block and erase it
     *
     * @return mixed
     * @throws \Exception
     */
    public function getResult()
    {
        if (is_null($this->_threadKey)) {
            return null;
        }

        $key = $this->_threadKey;
        $this->_threadKey = null;

        $cache = CacheContext::factory('phpthread');
        $result = $cache->get($key);
        $cache->release($key);

        if ($result instanceof \Exception) {
            throw $result;
        }

        return $result;
    }

    /**
     * Kill a thread
     *
     * @param int $signal
     * @param bool $wait
     */
    public function stop($signal = SIGKILL, $wait = false)
    {
        if ($this->isAlive()) {
            posix_kill($this->_pid, $signal);

            $status = null;
            if ($wait) {
                pcntl_waitpid($this->_pid, $status);
            }
        }
    }

    /**
     * Check if the forked process is alive
     * @return bool
     */
    public function isAlive()
    {
        $status = null;
        return (pcntl_waitpid($this->_pid, $status, WNOHANG) === 0);
    }

    /**
     * Handle the signal to the thread
     *
     * @param int $signal
     */
    private function signalHandler($signal)
    {
        switch ($signal) {
            case SIGTERM:
                exit(0);
        }
    }

    public function waitFinish()
    {
        pcntl_wait($status);
    }
}
