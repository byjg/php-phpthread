<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;

/**
 * Manage a pool of threads.
 *
 */
class ThreadPool
{

    /**
     * The list of threads
     * @var array
     */
    protected array $threadList = array();

    /**
     * The list of threads instances
     * @var array
     */
    protected array $threadInstance = array();

    protected bool $poolStarted = false;

    protected int $currentId = 0;

    /**
     * Add or enqueue a new thread worker.
     *
     * @param Closure $closure
     * @param array $params The thread parameters
     * @return int The ID of the queued worker.
     */
    public function addWorker(Closure $closure, ...$params): int
    {
        $thid = $this->currentId++;

        $data = new \stdClass;
        $data->closure = $closure;
        $data->params = $params;

        $this->threadList[$thid] = $data;

        if ($this->hasStarted()) {
            $this->runWorker($thid);
        }

        return $thid;
    }

    /**
     * Start all workers in the queue.
     */
    public function startAll(): void
    {
        if ($this->hasStarted()) {
            return;
        }

        foreach ($this->threadList as $key => $value) {
            $this->runWorker($key);
        }

        $this->poolStarted = true;
    }

    protected function runWorker(int $threadItemKey): void
    {
        $thread = Thread::create($this->threadList[$threadItemKey]->closure);
        $this->threadInstance[$threadItemKey] = $thread;
        $thread->start(...$this->threadList[$threadItemKey]->params);
    }

    /**
     * Stop or terminate all workers in the pool.
     */
    public function stopAll(): void
    {
        foreach ($this->threadList as $key => $value) {
            $this->removeWorker($key);
        }

        $this->poolStarted = false;
    }

    /**
     * Wait until all workers are finished.
     */
    public function waitForCompletion(): void
    {
        /** @var ThreadInterface $value */
        foreach ($this->threadInstance as $value) {
            $value->join();
        }
    }

    /**
     * Get the number of active workers.
     *
     * @return int
     */
    public function countActiveWorkers(): int
    {
        $count = 0;

        /** @var ThreadInterface $value */
        foreach ($this->threadInstance as $value) {
            $count += $value->isRunning() ? 1 : 0;
        }

        return $count;
    }

    /**
     * List all threads in the pool.
     *
     * @return array
     */
    public function listThreads(): array
    {
        return array_keys($this->threadInstance);
    }

    /**
     * Retrieve the result of a specific thread from shared memory.
     *
     * @param int $threadId
     * @return mixed
     */
    public function getThreadResult(int $threadId): mixed
    {
        if (!isset($this->threadInstance[$threadId])) {
            return null;
        }

        return $this->threadInstance[$threadId]->getResult();
    }

    /**
     * Check if a specific thread is running.
     *
     * @param int $threadId
     * @return bool|null Returns true if running, false if not, and null if thread ID is invalid.
     */
    public function isRunning(int $threadId): bool|null
    {
        $thread = $this->getThreadById($threadId);

        if (is_null($thread)) {
            return null;
        }

        return $thread->isRunning();
    }

    /**
     * Return a Thread object based on your id
     *
     * @param int $threadId
     * @return ThreadInterface|null
     */
    protected function getThreadById(int $threadId): ?ThreadInterface
    {
        if (!isset($this->threadInstance[$threadId])) {
            return null;
        }

        return $this->threadInstance[$threadId];
    }

    /**
     * Stop a specific worker.
     *
     * @param int $threadId
     * @param bool $remove Whether to remove the worker after stopping.
     * @return bool|null
     */
    public function stopWorker(int $threadId, bool $remove = true): bool|null
    {
        $thread = $this->getThreadById($threadId);

        if (is_null($thread)) {
            return null;
        } elseif ($thread->isRunning()) {
            $thread->terminate();
            if ($remove) {
                $this->removeWorker($threadId);
            }
            return true;
        } else {
            return false;
        }
    }

    /**
     * Remove a specific worker from the pool.
     *
     * @param int $threadId
     */
    public function removeWorker(int $threadId): void
    {
        $this->stopWorker($threadId, false);
        unset($this->threadInstance[$threadId]);
        unset($this->threadList[$threadId]);
    }

    /**
     * Check if the thread pool has been started.
     *
     * @return bool
     */
    public function hasStarted(): bool
    {
        return $this->poolStarted;
    }
}
