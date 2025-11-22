<?php

namespace ByJG\PHPThread\Handler;

use ByJG\Cache\Exception\InvalidArgumentException;
use ByJG\Cache\Exception\StorageErrorException;
use ByJG\PHPThread\SharedMemory;
use ByJG\PHPThread\ThreadStatus;
use Closure;
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

    private ?Closure $onFinish = null;

    /**
     * constructor method
     *
     */
    public function __construct(?Closure $onFinish = null)
    {
        if (!function_exists('pcntl_fork')) {
            throw new RuntimeException('PHP was compiled without --enable-pcntl or you are running on Windows.');
        }

        if (php_sapi_name() != 'cli') {
            throw new RuntimeException('Threads only works in CLI mode');
        }

        SharedMemory::getInstance();
        $this->onFinish = $onFinish;
    }

    /**
     * Private function for set the method will be forked;
     *
     * @param Closure $closure
     * @return void
     */
    #[\Override]
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
    #[\Override]
    public function start(mixed ...$args): void
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

            try {
                $return = call_user_func($this->closure, ...$args);

                if (!is_null($return)) {
                    $this->saveResult($return);
                }
                // Executed only in PHP 7, will not match in PHP 5.x
            } catch (Throwable $t) {
                $this->saveResult($t);
            }

            if (!empty($this->onFinish)) {
                call_user_func($this->onFinish);
            }

            exit(0);
        }
    }

    /**
     * Save the thread result in a shared memory block
     *
     * @param mixed $object Need to be serializable
     */
    protected function saveResult(mixed $object): void
    {
        if ($this->threadKey === null) {
            throw new RuntimeException('Thread key is not initialized');
        }
        SharedMemory::getInstance()->set($this->threadKey, $object);
    }

    /**
     * Get the thread result from the shared memory block and erase it
     *
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     * @throws Throwable
     */
    #[\Override]
    public function getResult(): mixed
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

        if ($this->threadResult instanceof Throwable) {
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
    #[\Override]
    public function terminate(int $signal = SIGKILL, bool $wait = false): void
    {
        if ($this->isRunning()) {
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
    #[\Override]
    public function isRunning(): bool
    {
        return (pcntl_waitpid($this->pid, $status, WNOHANG) === 0);
    }

    /**
     * Handle the signal to the thread
     *
     * @param int $signal
     */
    public function signalHandler(int $signal): void
    {
        if ($signal == SIGTERM) {
            exit(0);
        }
    }

    #[\Override]
    public function join(): void
    {
        //pcntl_wait($status);
        while ($this->isRunning()) {
            usleep(100);
        }
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    #[\Override]
    public function getClassName(): string
    {
        return ForkHandler::class;
    }

    #[\Override]
    public function getStatus(): ThreadStatus
    {
        if (empty($this->threadKey)) {
            return ThreadStatus::notStarted;
        }

        return $this->isRunning() ? ThreadStatus::running : ThreadStatus::finished;
    }
}
