<?php

namespace ByJG\PHPThread\Handler;

use Composer\Autoload\ClassLoader;

class PThreadHandler extends \Thread implements ThreadInterface
{
    /**
     * @var ClassLoader
     */
    private $loader;

    /**
     * @var callable
     */
    private $callable;

    private $args;

    private $result;

    /**
     * Thread constructor.
     */
    public function __construct()
    {
        $this->getLoader();
    }

    /**
     * @return ClassLoader
     */
    public function getLoader()
    {
        if (!is_null($this->loader)) {
            return $this->loader;
        }

        $path = __DIR__ . '/../../vendor/autoload.php';
        if (!file_exists($path)) {
            $path = __DIR__ . '/../../../../autoload.php';
            if (!file_exists($path)) {
                throw new \RuntimeException("Autoload path '$path' not found");
            }
        }
        $this->loader = require("$path");

        return $this->loader;
    }

    /**
     * Here you are in a threaded environment
     */
    public function run()
    {
        register_shutdown_function([$this, 'threadError']);

        $this->getLoader()->register();

        $callable = $this->callable;
        if (!is_string($callable)) {
            $callable = (array) $this->callable;
        }

        try {
            $this->result = call_user_func_array($callable, (array)$this->args);
        } catch (\Exception $ex) {
            $this->result = $ex;
        }
    }

    /**
     * Start the thread
     *
     * @throws \RuntimeException
     */
    public function execute()
    {
        $this->args = func_get_args();
        return parent::start();
    }

    /**
     * Get the thread result
     *
     * @return mixed
     * @throws \Exception
     */
    public function getResult()
    {
        if ($this->result instanceof \Exception) {
            throw $this->result;
        }
        return $this->result;
    }

    /**
     * Kill a thread
     *
     * @param int $signal
     * @param bool $wait
     */
    public function stop($signal = SIGKILL, $wait = false)
    {
        parent::kill();
    }

    /**
     * Checkif the thread is not Terminated
     *
     * @return bool
     */
    public function isAlive()
    {
        if ($this->isRunning()) {
            return true;
        }

        if (!$this->isJoined()) {
            $this->join();
        }

        return false;
    }

    /**
     * Set the thread callable method
     * @param callable $callable
     * @return mixed
     */
    public function setCallable(callable $callable)
    {
        $this->callable = $callable;
    }

    public function waitFinish()
    {
        $this->join();
    }
}
