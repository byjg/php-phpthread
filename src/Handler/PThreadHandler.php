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
        $this->getLoader()->register();

        $callable = $this->callable;
        if (!is_string($callable)) {
            $callable = (array) $this->callable;
        }

        try {
            $this->result = call_user_func_array($callable, (array)$this->args);
        // Executed only in PHP 7, will not match in PHP 5.x
        } catch (\Throwable $ex) {
            $this->result = $ex;
        // Executed only in PHP 5. Remove when PHP 5.x is no longer necessary.
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
     * @throws \Error
     * @throws \Throwable
     */
    public function getResult()
    {
        $result = $this->result;
        if (is_object($result) &&
            ($result instanceof \Exception
                || $result instanceof \Throwable
                || $result instanceof \Error
            )
        ) {
            throw $result;
        }

        return $result;
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
