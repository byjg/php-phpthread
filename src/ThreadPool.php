<?php

namespace ByJG\PHPThread;

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
    protected $threadList = array();

    /**
     * The list of threads instances
     * @var array
     */
    protected $threadInstance = array();

    protected $poolStarted = false;

    /**
     * Queue a new thread worker
     *
     * @param Closure $closure
     * @param array $params The thread parameters
     * @param string $thid The Thread id to identify the ID
     * @return string
     */
    public function queueWorker(Closure $closure, $params = [], $thid = null)
    {
        if (!is_array($params)) {
            throw new \InvalidArgumentException('The params needs to be an array');
        }

        if (is_null($thid)) {
            $thid = uniqid("", true);
        }

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
    public function startPool()
    {
        foreach ($this->threadList as $key => $value) {
            $this->startWorker($key);
        }

        $this->poolStarted = true;
    }

    protected function startWorker($threadItemKey)
    {
        $thread = new Thread($this->threadList[$threadItemKey]->closure);

        call_user_func_array([$thread, 'execute'], $this->threadList[$threadItemKey]->params);
        $this->threadInstance[$threadItemKey] = $thread;
    }

    /**
     * Stop all the workers in the queue
     */
    public function stopPool()
    {
        foreach ($this->threadList as $key => $value) {
            $this->removeWorker($key);
        }

        $this->poolStarted = false;
    }

    /**
     * Wait until all workers are finished
     */
    public function waitWorkers()
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
    public function activeWorkers()
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
    public function getThreads()
    {
        return array_keys($this->threadInstance);
    }

    /**
     * Get the thread result from the Shared Memory
     *
     * @param string $threadId
     * @return mixed
     */
    public function getThreadResult($threadId)
    {
        if (!isset($this->threadInstance[$threadId])) {
            return null;
        }

        return $this->threadInstance[$threadId]->getResult();
    }

    /**
     * Check if the thread is running or not
     *
     * @param string $threadId
     * @return bool
     */
    public function isAlive($threadId)
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
     * @param string $threadId
     * @return Thread
     */
    protected function getThreadById($threadId)
    {
        if (!isset($this->threadInstance[$threadId])) {
            return null;
        }

        return $this->threadInstance[$threadId];
    }

    /**
     * Stops a specific thread
     *
     * @param string $threadId
     * @param bool $remove
     * @return bool
     */
    public function stopWorker($threadId, $remove = true)
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

    public function removeWorker($threadId)
    {
        $this->stopWorker($threadId, false);
        unset($this->threadInstance[$threadId]);
        unset($this->threadList[$threadId]);
    }

    public function isPoolStarted()
    {
        return $this->poolStarted;
    }
}
