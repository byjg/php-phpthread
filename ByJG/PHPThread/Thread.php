<?php

namespace ByJG\PHPThread;

use Exception;

class Thread
{
    public function __construct($callback)
    {
        if (!function_exists('pcntl_fork'))
            throw new Exception('PHP was compiled without --enable-pcntl or you are running on Windows.');

        if ($callback == null)
			throw new Exception('The callback function is required.');

		$this->setCallback($callback);
    }

    public function isAlive()
    {
        return (pcntl_waitpid($this->_pid, $status, WNOHANG) === 0);
    }

    protected function setCallback($callback)
    {
		if (is_array($callback)) {
			if ((count($callback) == 2) && method_exists($callback[0], $callback[1])  && is_callable($callback))
				$this->_callback = $callback;
			elseif (count($callback) != 2)
				throw new Exception("The parameter need to be a two elements array with a instance and a method of this instance or just a PHP static function");
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
				throw new Exception("The method " . $className . "->". $callback[1] . "() does not exists or not is callable");
			}
		}
		elseif (function_exists($callback) && is_callable($callback))
			$this->_callback = $callback;
		else
			throw new Exception("$callback is not valid function");
    }

    public function start()
    {
        if (($this->_pid = pcntl_fork()) == -1)
            throw new Exception('Couldn\'t fork the process');

        if (!$this->_pid) {
            // Child.

            pcntl_signal(SIGTERM, array($this, 'SignalHandler'));

            $args = func_get_args();

            !empty($args) ? call_user_func_array($this->_callback, $args) : call_user_func($this->_callback);

            exit(0);
        }

        // Parent.
    }

    public function stop($signal = SIGKILL, $wait = false)
    {
        if ($this->IsAlive())
		{
            posix_kill($this->_pid, $signal);

	        if ($wait)
				pcntl_waitpid($this->_pid, $status);
        }
    }

    private function signalHandler($signal)
    {
        switch ($signal) {
            case SIGTERM:
                exit(0);
        }
    }

    private $_callback, $_pid;
}