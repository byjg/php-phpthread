<?php

namespace ByJG\PHPThread\Handler;

use ByJG\Cache\Psr16\ShmopCacheEngine;
use ByJG\PHPThread\SharedMemory;
use ByJG\PHPThread\Thread;
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
    protected $threadKey = null;
    private $closure;
    private $pid;

    private $threadResult = null;


    /**
     * constructor method
     *
     * @param int $maxSharedMemorySize
     * @param string $defaultPermission
     */
    public function __construct()
    {
        if (!function_exists('pcntl_fork')) {
            throw new RuntimeException('PHP was compiled without --enable-pcntl or you are running on Windows.');
        }
    }

    /**
     * Private function for set the method will be forked;
     *
     * @param \Closure $closure
     * @return mixed|void
     */
    public function setClosure(\Closure $closure)
    {
        $this->closure = $closure;
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

            try {
                $return = call_user_func_array($this->closure, (array)$args);

                if (!is_null($return)) {
                    $this->saveResult($return);
                }
            // Executed only in PHP 7, will not match in PHP 5.x
            } catch (\Throwable $t) {
                $this->saveResult($t);
            }

            exit(0);
        }
    }

    /**
     * Save the thread result in a shared memory block
     *
     * @param mixed $object Need to be serializable
     */
    protected function saveResult($object)
    {
        SharedMemory::getInstance()->set($this->threadKey, $object);
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

        if (!empty($this->threadResult)) {
            return $this->threadResult;
        }

        $key = $this->threadKey;
        $this->threadKey = null;

        $this->threadResult = SharedMemory::getInstance()->get($key);
        SharedMemory::getInstance()->delete($key);

        if ($this->threadResult instanceof \Throwable) {
            throw $this->threadResult;
        }

        return $this->threadResult;
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
        //pcntl_wait($status);
        if ($this->isAlive()) {
            usleep(50000);
            $this->waitFinish();
        }
    }

    public function getClassName()
    {
        return ForkHandler::class;
    }

    public function getStatus()
    {
        if (empty($this->threadKey)) {
            return Thread::STATUS_NOT_STARTED;
        }

        return $this->isAlive() ? Thread::STATUS_RUNNING : Thread::STATUS_FINISHED;
    }
}
