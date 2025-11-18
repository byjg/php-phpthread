<?php
require __DIR__ . '/../vendor/autoload.php';

// Create a instance of the ThreadPool
$threadPool = new \ByJG\PHPThread\ThreadPool();

$fn = function ($arg) {
    sleep(1);
    echo $arg . "\n";
    return $arg * 2;
};

// Create and queue the threads with call parameters
$threadPool->addWorker($fn, 1);
$threadPool->addWorker($fn, 2);

// Starts all the threads in the queue
$threadPool->startAll();

// Add more workers after the pool is started:
$threadPool->addWorker($fn, 3);
$threadPool->addWorker($fn, 4);

// Wait until there is no more active workers
$threadPool->waitForCompletion();

// Get the return value from the thread.
foreach ($threadPool->listThreads() as $thid) {
    echo 'Result: ' . $threadPool->getThreadResult($thid) . "\n";
}

echo "\n\nEnded!\n";


class Xyz
{
    protected function getClosure()
    {
        return function ($arg) {
            usleep(100 * $arg);
            return $arg * 3;
        };
    }

    public function testThread()
    {
        $pool = new \ByJG\PHPThread\ThreadPool();

        $th1 = $pool->addWorker($this->getClosure(), 3);
        $th2 = $pool->addWorker($this->getClosure(), 2);
        echo $pool->countActiveWorkers() . "\n";

        $pool->startAll();
        echo $pool->countActiveWorkers() . "\n";

        $th3 = $pool->addWorker($this->getClosure(), 1);
        echo $pool->countActiveWorkers() . "\n";

        $pool->waitForCompletion();

        echo $pool->countActiveWorkers() . "\n";

        echo $pool->getThreadResult($th1) . "\n";
        echo $pool->getThreadResult($th2) . "\n";
        echo $pool->getThreadResult($th3) . "\n";
    }
}


$xyz = new Xyz();
$xyz->testThread();