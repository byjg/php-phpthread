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

        $path = realpath(__DIR__ . '/../../vendor/autoload.php');
        if (!file_exists($path)) {
            $path = realpath(__DIR__ . '/../../../autoload.php');
            if (!file_exists($path)) {
                throw new \RuntimeException("Autoload path '$path' not found");
            }
        }
        $this->loader = require_once "$path";

        return $this->loader;
    }

    /**
     * Here you are in a threaded environment
     */
    public function run()
    {
        $this->getLoader()->register();

        $this->result = call_user_func($this->callable, $this->args);
    }

    /**
     * Start the thread
     *
     * @throws \RuntimeException
     */
    public function start()
    {
        $this->args = func_get_args();
        return parent::start();
    }

    /**
     * Get the thread result
     *
     * @return mixed
     */
    public function getResult()
    {
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
        if (!$this->isTerminated()) {
            return true;
        }

        if (!$this->isJoined()) {
            $this->join();
        }

        return false;
    }

    /**
     * Set the thread callable method
     * @param callable $callback
     * @return mixed
     */
    public function setCallback(callable $callback)
    {
        $this->callable = $callback;
    }
}
