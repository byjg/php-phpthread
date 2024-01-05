## Thread Pool Usage

You can create a pool of threads. This is particulary interesting if you want to queue Workers after the pool is started.

```php
// Create a instance of the ThreadPool
$threadPool = new \ByJG\PHPThread\ThreadPool();

// Create and queue the threads with call parameters
$threadPool->queueWorker( $threadClousure, [ 1 ]);
$threadPool->queueWorker( $threadClousure, [ 2 ]);

// Starts all the threads in the queue
$threadPool->startPool();

// Add more workers after the pool is started:
$threadPool->queueWorker( $threadClousure, [ 3 ]);
$threadPool->queueWorker( $threadClousure, [ 4 ]);

// Wait until there is no more active workers
$threadPool->waitWorkers();

// Get the return value from the thread.
foreach ($threadPool->getThreads() as $thid) {
    echo 'Result: ' . $threadPool->getThreadResult($thid) . "\n";
}

echo "\n\nEnded!\n";
```
