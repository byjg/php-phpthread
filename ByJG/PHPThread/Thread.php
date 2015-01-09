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

	/**
	 * Class constructor
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
	 *
	 * @throws RuntimeException
	 */
	public function start()
	{
		if (($this->_pid = pcntl_fork()) == -1)
		{
			throw new RuntimeException('Couldn\'t fork the process');
		}

		if (!$this->_pid)
		{
			// Child.
			pcntl_signal(SIGTERM, array($this, 'signalHandler'));
			$args = func_get_args();
			!empty($args) ? call_user_func_array($this->_callback, $args) : call_user_func($this->_callback);

			exit(0);
		}

		// Parent.
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
