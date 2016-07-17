<?php

require_once('vendor/autoload.php');

// Method to be executed in a thread
function Foo($t)
{
    echo "Starting thread #$t" . PHP_EOL;;
    sleep(1 * rand(1, 5));
    for ($i = 0; $i < 10; $i++) {
        echo "Hello from thread #$t, i=$i" . PHP_EOL;
        sleep(1);
    }
    echo "Ending thread #$t" . PHP_EOL;

    return uniqid("Thread_{$t}_");
}


try {
    // Create a instance of the ThreadPool
    $threadPool = new \ByJG\PHPThread\ThreadPool();

    // Create the threads
    for ($i = 0; $i < 10; $i++) {
        // Queue a worker pointing to "Foo" function and pass the required parameters
        $threadPool->queueWorker('Foo', [$i]);
    }

    // Starts all the threads in the queue
    $threadPool->startWorkers();

    // Wait until there is no more active workers
    while ($threadPool->activeWorkers() > 0) {
        echo "Active Workers : " . $threadPool->activeWorkers() . "\n";
        sleep(1);
    }

    // Get the return value from the thread.
    foreach ($threadPool->getThreads() as $thid) {
        echo 'Result: ' . $threadPool->getThreadResult($thid) . "\n";
    }

    echo "\n\nEnded!\n";

} catch (Exception $e) {
    echo 'Exception: ' . $e . PHP_EOL;
}

