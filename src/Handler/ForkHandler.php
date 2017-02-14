<?php

namespace ByJG\PHPThread\Handler;

use ByJG\Cache\CacheContext;
use ByJG\Cache\Engine\ShmopCacheEngine;
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
    protected $threadKey;
    private $callable;
    private $pid;


    private $maxSharedMemorySize = null;
    private $defaultPermission = null;

    /**
     * constructor method
     *
     * @param int $maxSharedMemorySize
     * @param string $defaultPermission
     */
    public function __construct($maxSharedMemorySize = 0x100000, $defaultPermission = '0700')
    {
        if (!function_exists('pcntl_fork')) {
            throw new RuntimeException('PHP was compiled without --enable-pcntl or you are running on Windows.');
        }

        $this->maxSharedMemorySize = $maxSharedMemorySize;
        $this->defaultPermission = $defaultPermission;
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
        $this->threadKey = 'thread_' . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999) . rand(1000, 9999);

        if (($this->pid = pcntl_fork()) == -1) {
            throw new RuntimeException('Couldn\'t fork the process');
        }

        if ($this->pid) {
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
            // Executed only in PHP 7, will not match in PHP 5.x
            } catch (\Throwable $t) {
                $this->saveResult($t);
            // Executed only in PHP 5. Remove when PHP 5.x is no longer necessary.
            } catch (\Exception $ex) {
                $this->saveResult($ex);
            }

            exit(0);
        }
    }

    /**
     * @return \ByJG\Cache\Engine\ShmopCacheEngine
     */
    protected function getSharedMemoryEngine()
    {
        return new ShmopCacheEngine(
            [
                'max-size' => $this->maxSharedMemorySize,
                'default-permission' => $this->defaultPermission
            ]
        );
    }

    /**
     * Save the thread result in a shared memory block
     *
     * @param mixed $object Need to be serializable
     */
    protected function saveResult($object)
    {
        $this->getSharedMemoryEngine()->set($this->threadKey, $object);
    }

    /**
     * Get the thread result from the shared memory block and erase it
     *
     * @return mixed
     * @throws \Error
     * @throws object
     */
    public function getResult()
    {
        if (is_null($this->threadKey)) {
            return null;
        }

        $key = $this->threadKey;
        $this->threadKey = null;

        $cache = $this->getSharedMemoryEngine();
        $result = $cache->get($key);
        $cache->release($key);

        if (is_object($result) &&
            ($result instanceof \Exception
                || $result instanceof \Throwable
                || $result instanceof \Error
            )
        ) {
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
            posix_kill($this->pid, $signal);

            $status = null;
            if ($wait) {
                pcntl_waitpid($this->pid, $status);
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
        return (pcntl_waitpid($this->pid, $status, WNOHANG) === 0);
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
