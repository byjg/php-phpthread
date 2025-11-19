---
sidebar_position: 2
---

# Thread Pool

## Thread Pool Usage

You can create a pool of threads. This is particulary interesting if you want to queue Workers
after the pool is started.

```php
// Create a instance of the ThreadPool
$threadPool = new \ByJG\PHPThread\ThreadPool();

// Create and queue the threads with call parameters
$threadPool->addWorker( $threadClousure, 1);
$threadPool->addWorker( $threadClousure, 2);

// Starts all the threads in the queue
$threadPool->startAll();

// Add more workers after the pool is started:
$threadPool->addWorker( $threadClousure, 3);
$threadPool->addWorker( $threadClousure, 4);

// Wait until there is no more active workers
$threadPool->waitForCompletion();

// Get the return value from the thread.
foreach ($threadPool->listThreads() as $thid) {
    echo 'Result: ' . $threadPool->getThreadResult($thid) . "\n";
}

echo "\n\nEnded!\n";
```

While the pool is running, you can add more workers to the pool. 
