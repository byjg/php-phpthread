<?php

namespace ByJG\PHPThread;

use InvalidArgumentException;
use RuntimeException;

/**
 * Native Implementation of Threads in PHP.
 *
 * A class to spawn a thread. Only works in *nix environments,
 * as Windows platform is missing libpcntl.
 *
 * Forks the process.
 */
class Thread
{

	protected $uniqId;
	protected $shmKey;

	protected static $lastShmKey = 0xf00;

	protected $_threadResult = null;


	/**
	 * constructor method
	 *
	 * @param mixed $callback string with the function name or a array with the instance and the method name
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 */
	public function __construct($callback)
	{
		if (!function_exists('pcntl_fork'))
		{
			throw new RuntimeException('PHP was compiled without --enable-pcntl or you are running on Windows.');
		}

		if ($callback == null)
		{
			throw new InvalidArgumentException('The callback function is required.');
		}

		$this->shmKey = self::$lastShmKey++;
		$this->setCallback($callback);
	}

	public function __destruct()
	{
		if (file_exists($this->uniqId))
		{
			unlink($this->uniqId);
		}
	}

	/**
	 * Check if the forked process is alive
	 * @return bool
	 */
	public function isAlive()
	{
		return (pcntl_waitpid($this->_pid, $status, WNOHANG) === 0);
	}

	/**
	 * Private function for set the method will be forked;
	 *
	 * @param string $callback string with the function name or a array with the instance and the method name
	 * @throws InvalidArgumentException
	 */
	protected function setCallback($callback)
	{
		if (is_array($callback))
		{
			if ((count($callback) == 2) && method_exists($callback[0], $callback[1]) && is_callable($callback))
			{
				$this->_callback = $callback;
			}
			elseif (count($callback) != 2)
			{
				throw new InvalidArgumentException("The parameter need to be a two elements array with a instance and a method of this instance or just a PHP static function");
			}
			else
			{
				if (is_object($callback[0]))
				{
					$className = get_class($callback[0]);
				}
				else
				{
					$className = $callback[0];
				}
				throw new InvalidArgumentException("The method " . $className . "->" . $callback[1] . "() does not exists or not is callable");
			}
		}
		elseif (function_exists($callback) && is_callable($callback))
		{
			$this->_callback = $callback;
		}
		else
		{
			throw new InvalidArgumentException("$callback is not valid function");
		}
	}

	/**
	 *
	 * @throws RuntimeException
	 */
	public function start()
	{
		if (($this->_pid = pcntl_fork()) == -1)
		{
			throw new RuntimeException('Couldn\'t fork the process');
		}

		if ($this->_pid)
		{
			// Parent
			//pcntl_wait($status); //Protect against Zombie children
		}
		else
		{
			// Child.
			pcntl_signal(SIGTERM, array($this, 'signalHandler'));
			$args = func_get_args();
			if ((count($args) == 1) && ($args[0] instanceof \stdClass) && (isset($args[0]->thread1234)))
			{
				$args = $args[0]->thread1234;
			}
			if (!empty($args))
			{
				$return = call_user_func_array($this->_callback, $args);
			}
			else
			{
				$return = call_user_func($this->_callback);
			}

			if (!is_null($return))
			{
				$this->saveResult($return);
			}

			exit(0);
		}

		// Parent.
	}

	protected function saveResult($object)
	{
		$serialized = serialize($object);
		$shm_id = shmop_open($this->shmKey, "c", 0644, strlen($serialized));
		shmop_write($shm_id, $serialized, 0);
		shmop_close($shm_id);
	}

	public function getResult()
	{
		$shm_id = shmop_open($this->shmKey, "a", 0644, 16*1024);
		$serialized = shmop_read($shm_id, 0, shmop_size($shm_id));
		shmop_close($shm_id);

		return unserialize($serialized);
	}

	/**
	 *
	 * @param type $signal
	 * @param type $wait
	 */
	public function stop($signal = SIGKILL, $wait = false)
	{
		if ($this->isAlive())
		{
			posix_kill($this->_pid, $signal);

			if ($wait)
			{
				pcntl_waitpid($this->_pid, $status);
			}
		}
	}

	/**
	 *
	 * @param type $signal
	 */
	private function signalHandler($signal)
	{
		switch ($signal)
		{
			case SIGTERM:
				exit(0);
		}
	}

	private $_callback, $_pid;

}
