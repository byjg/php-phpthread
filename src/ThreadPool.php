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
    protected $_threadList = array();

    /**
     * The list of threads instances
     * @var array
     */
    protected $_threadInstance = array();

    /**
     * Queue a new thread worker
     *
     * @param mixed $callback
     * @param array $params The thread parameters
     * @param string $thid The Thread id to identify the ID
     * @return type
     * @throws \InvalidArgumentException
     */
    public function queueWorker($callback, $params = null, $thid = null)
    {
        if (!is_string($callback) && (is_array($callback) && count($callback) != 2)) {
            throw new \InvalidArgumentException('The callback needs to be a value compatible with call_user_func()');
        }

        if (!is_null($params) && !is_array($params)) {
            throw new \InvalidArgumentException('The params needs to be an array');
        }

        if (is_null($thid)) {
            $thid = uniqid("", true);
        }

        $data = new \stdClass;
        $data->callback = $callback;
        $data->params = $params;

        $this->_threadList[$thid] = $data;

        return $thid;
    }

    /**
     * Start all the workers in the queue
     */
    public function startWorkers()
    {
        $thr = array();

        foreach ($this->_threadList as $key => $value) {
            $thread = new Thread($value->callback);

            $params = new \stdClass;
            $params->thread1234 = $value->params;

            $thread->start($params);
            $thr[$key] = $thread;
        }

        $this->_threadInstance = $thr;
    }

    /**
     * How many workers are active
     *
     * @return int
     */
    public function activeWorkers()
    {
        $count = 0;

        foreach ($this->_threadInstance as $value) {
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
        return array_keys($this->_threadInstance);
    }

    /**
     * Return a Thread object based on your id
     *
     * @param string $threadId
     * @return Thread
     */
    protected function getThreadById($threadId)
    {
        if (!isset($this->_threadInstance[$threadId])) {
            return null;
        }

        return $this->_threadInstance[$threadId];
    }

    /**
     * Get the thread result from the Shared Memory
     *
     * @param string $threadId
     * @return mixed
     */
    public function getThreadResult($threadId)
    {
        if (!isset($this->_threadInstance[$threadId])) {
            return null;
        }

        return $this->_threadInstance[$threadId]->getResult();
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
