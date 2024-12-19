<?php

namespace ByJG\PHPThread\Handler;

use ByJG\Cache\Exception\InvalidArgumentException;
use ByJG\Cache\Exception\StorageErrorException;
use ByJG\PHPThread\SharedMemory;
use ByJG\PHPThread\ThreadStatus;
use Closure;
use Error;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

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
    protected ?string $threadKey = null;
    private Closure $closure;
    private int $pid;

    private mixed $threadResult = null;


    /**
     * constructor method
     *
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
     * @param Closure $closure
     * @return void
     */
    public function setClosure(Closure $closure): void
    {
        $this->closure = $closure;
    }

    /**
     * Start the thread
     *
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws StorageErrorException
     */
    public function execute(): void
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
            } catch (Throwable $t) {
                $this->saveResult($t);
            }

            exit(0);
        }
    }

    /**
     * Save the thread result in a shared memory block
     *
     * @param mixed $object Need to be serializable
     * @throws InvalidArgumentException
     * @throws StorageErrorException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function saveResult(mixed $object): void
    {
        SharedMemory::getInstance()->set($this->threadKey, $object);
    }

    /**
     * Get the thread result from the shared memory block and erase it
     *
     * @return mixed
     * @throws Error
     * @throws object
     */
    public function getResult(): mixed
    {
        if (is_null($this->threadKey)) {
            return null;
        }

        if (!empty($this->threadResult)) {
            return $this->threadResult;
        }

        $this->threadResult = SharedMemory::getInstance()->get($this->threadKey);

        if ($this->threadResult instanceof Throwable) {
            throw $this->threadResult;
        }

        return $this->threadResult;
    }

    public function __destruct()
    {
        if (isset($this->pid) && $this->pid && !empty($this->threadKey)) {
            echo "Destructing Thread {$this->threadKey} \n";
            SharedMemory::getInstance()->delete($this->threadKey);
        }
    }

    /**
     * Kill a thread
     *
     * @param int $signal
     * @param bool $wait
     */
    public function stop(int $signal = SIGKILL, bool $wait = false): void
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
    public function isAlive(): bool
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

    public function waitFinish(): void
    {
        //pcntl_wait($status);
        if ($this->isAlive()) {
            usleep(50000);
            $this->waitFinish();
        }
    }

    public function getClassName(): string
    {
        return ForkHandler::class;
    }

    public function getStatus(): ThreadStatus
    {
        if (empty($this->threadKey)) {
            return ThreadStatus::notStarted;
        }

        return $this->isAlive() ? ThreadStatus::running : ThreadStatus::finished;
    }
}
