<?php

namespace ByJG\PHPThread;

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

    /**
     * Queue a new thread worker
     *
     * @param callable $callable
     * @param array $params The thread parameters
     * @param string $thid The Thread id to identify the ID
     * @return Thread
     * @throws \InvalidArgumentException
     */
    public function queueWorker(callable $callable, $params = [], $thid = null)
    {
        if (!is_array($params)) {
            throw new \InvalidArgumentException('The params needs to be an array');
        }

        if (is_null($thid)) {
            $thid = uniqid("", true);
        }

        $data = new \stdClass;
        $data->callable = $callable;
        $data->params = $params;

        $this->threadList[$thid] = $data;

        return $thid;
    }

    /**
     * Start all the workers in the queue
     */
    public function startWorkers()
    {
        $thr = array();

        foreach ($this->threadList as $key => $value) {
            $thread = new Thread($value->callable);

            call_user_func_array([$thread, 'execute'], $value->params);
            $thr[$key] = $thread;
        }

        $this->threadInstance = $thr;
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
     * @return boolean
     */
    public function stopWorker($threadId)
    {
        $thread = $this->getThreadById($threadId);

        if (is_null($thread)) {
            return null;
        } elseif ($thread->isAlive()) {
            $thread->stop();
            return true;
        } else {
            return false;
        }
    }
}
