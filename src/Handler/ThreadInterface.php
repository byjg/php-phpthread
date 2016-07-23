<?php

namespace ByJG\PHPThread\Handler;

interface ThreadInterface
{

    /**
     * Start the thread
     *
     * @throws \RuntimeException
     */
    public function execute();

    /**
     * Get the thread result
     *
     * @return mixed
     */
    public function getResult();

    /**
     * Kill a thread
     *
     * @param int $signal
     * @param bool $wait
     */
    public function stop($signal = SIGKILL, $wait = false);

    /**
     * Checkif the thread is not Terminated
     *
     * @return bool
     */
    public function isAlive();

    /**
     * Set the thread callable method
     * @param callable $callable
     * @return mixed
     */
    public function setCallable(callable $callable);

    /**
     * Wait for the thread finish and join to main thread;
     *
     * @return mixed
     */
    public function waitFinish();
}
