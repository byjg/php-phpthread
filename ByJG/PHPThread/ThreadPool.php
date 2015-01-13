<?php

namespace ByJG\PHPThread;

class ThreadPool
{

	protected $_threadList = array();

	protected $_threadInstance = array();

	public function queueWorker($callback, $params = null)
	{
		if (!is_string($callback) || (is_array($callback) && count($callback) != 2))
		{
			throw new \InvalidArgumentException('The callback needs to be a value compatible with call_user_func()');
		}

		if (!is_null($params) && !is_array($params))
		{
			throw new \InvalidArgumentException('The params needs to be an array');
		}

		$id = uniqid("", true);

		$data = new \stdClass;
		$data->callback = $callback;
		$data->params = $params;

		$this->_threadList[$id] = $data;

		return $id;
	}

	public function startWorkers()
	{
		$thr = array();

		foreach ($this->_threadList as $key => $value)
		{
			$thread = new Thread($value->callback);

			$params = new \stdClass;
			$params->thread1234 = $value->params;

			$thread->start($params);
			$thr[$key] = $thread;
		}

		$this->_threadInstance = $thr;
	}

	public function activeWorkers()
	{
		$count = 0;

		foreach ($this->_threadInstance as $key => $value)
		{
			$count += $value->isAlive() ? 1 : 0;
		}

		return $count;
	}

	public function getThreads()
	{
		return array_keys($this->_threadInstance);
	}

	/**
	 *
	 * @param type $id
	 * @return Thread
	 */
	protected function getThreadById($id)
	{
		if (!isset($this->_threadInstance[$id]))
		{
			return null;
		}

		return $this->_threadInstance[$id];
	}


	public function getThreadResult($id)
	{
		if (!isset($this->_threadInstance[$id]))
		{
			return null;
		}
		
		return $this->_threadInstance[$id]->getResult();
	}

	public function isAlive($id)
	{
		$thread = $this->getThreadById($id);

		if (is_null($thread))
		{
			return null;
		}
		else
		{
			return $thread->isAlive();
		}
	}

	public function stopWorker($id)
	{
		$thread = $this->getThreadById($id);

		if (is_null($thread))
		{
			return null;
		}
		elseif ($thread->isAlive())
		{
			$thread->stop();
			return true;
		}
		else
		{
			return false;
		}
	}

}