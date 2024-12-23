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
     * Queue a new thread worker
     *
     * @param Closure $closure
     * @param array $params The thread parameters
     * @return int
     */
    public function queueWorker(Closure $closure, ...$params): int
    {
        $thid = $this->currentId++;

        $data = new \stdClass;
        $data->closure = $closure;
        $data->params = $params;

        $this->threadList[$thid] = $data;

        if ($this->isPoolStarted()) {
            $this->startWorker($thid);
        }

        return $thid;
    }

    /**
     * Start all the workers in the queue
     */
    public function startPool(): void
    {
        if ($this->isPoolStarted()) {
            return;
        }

        foreach ($this->threadList as $key => $value) {
            $this->startWorker($key);
        }

        $this->poolStarted = true;
    }

    protected function startWorker(int $threadItemKey): void
    {
        $thread = Thread::create($this->threadList[$threadItemKey]->closure);
        $this->threadInstance[$threadItemKey] = $thread;
        $thread->execute(...$this->threadList[$threadItemKey]->params);
    }

    /**
     * Stop all the workers in the queue
     */
    public function stopPool(): void
    {
        foreach ($this->threadList as $key => $value) {
            $this->removeWorker($key);
        }

        $this->poolStarted = false;
    }

    /**
     * Wait until all workers are finished
     */
    public function waitWorkers(): void
    {
        foreach ($this->threadInstance as $value) {
            $value->waitFinish();
        }
    }

    /**
     * How many workers are active
     *
     * @return int
     */
    public function activeWorkers(): int
    {
        $count = 0;

        foreach ($this->threadInstance as $value) {
            $count += $value->isAlive() ? 1 : 0;
        }

        return $count;
    }

    /**
     * Return a list of threads
     *
     * @return array
     */
    public function getThreads(): array
    {
        return array_keys($this->threadInstance);
    }

    /**
     * Get the thread result from the Shared Memory
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
     * Check if the thread is running or not
     *
     * @param int $threadId
     * @return bool
     */
    public function isAlive(int $threadId): bool|null
    {
        $thread = $this->getThreadById($threadId);

        if (is_null($thread)) {
            return null;
        }

        return $thread->isAlive();
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
     * Stops a specific thread
     *
     * @param int $threadId
     * @param bool $remove
     * @return bool|null
     */
    public function stopWorker(int $threadId, bool $remove = true): bool|null
    {
        $thread = $this->getThreadById($threadId);

        if (is_null($thread)) {
            return null;
        } elseif ($thread->isAlive()) {
            $thread->stop();
            if ($remove) {
                $this->removeWorker($threadId);
            }
            return true;
        } else {
            return false;
        }
    }

    public function removeWorker(int $threadId): void
    {
        $this->stopWorker($threadId, false);
        unset($this->threadInstance[$threadId]);
        unset($this->threadList[$threadId]);
    }

    public function isPoolStarted(): bool
    {
        return $this->poolStarted;
    }
}
