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

	const maxResultSize = 0x40000; // 256kb

	/**
	 * The Id of the shared memory block
	 * @var long
	 */
	protected $_shmKey;

	/**
	 * The next id of the shared memory block available
	 * @var type
	 */
	protected static $_lastShmKey = 0xf00;


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

		$this->_shmKey = self::$_lastShmKey++;
		$this->setCallback($callback);
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
	 * Start the thread
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

	/**
	 * Save the thread result in a shared memory block
	 *
	 * @param mixed $object Need to be serializable
	 */
	protected function saveResult($object)
	{
		$serialized = serialize($object);
		$size = strlen($serialized);
		if ($size < self::maxResultSize)
		{
			$shm_id = @shmop_open($this->_shmKey, "c", 0644, $size);
			if(!$shm_id)
			{
				throw new Exception("Couldn't create shared memory segment");
			}
			$shm_bytes_written = shmop_write($shm_id, $serialized, 0);
			if ($shm_bytes_written != $size)
			{
				warn("Couldn't write the entire length of data");
			}
			shmop_close($shm_id);
		}
		else
		{
			throw new \OverflowException('The response of the thread was greater then ' . self::maxResultSize . ' bytes.');
		}
	}

	/**
	 * Get the thread result from the shared memory block and erase it
	 * 
	 * @return mixed
	 */
	public function getResult()
	{
		$shm_id = @shmop_open($this->_shmKey, "a", 0644, self::maxResultSize);
		if(!$shm_id)
		{
			return null;
		}

		$serialized = shmop_read($shm_id, 0, shmop_size($shm_id));
		shmop_delete($shm_id);
		shmop_close($shm_id);

		return unserialize($serialized);
	}

	/**
	 * Kill a thread
	 *
	 * @param int $signal
	 * @param bool $wait
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
	 * Handle the signal to the thread
	 *
	 * @param int $signal
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
