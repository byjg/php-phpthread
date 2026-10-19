---
sidebar_position: 1
---

# Thread

Assume for the examples below the class 'Foo' and the method 'bar':

```php
require_once('vendor/autoload.php');

// Method to be executed in a thread
$threadClousure = function ($t)
    {
        echo "Starting thread #$t" . PHP_EOL;;
        sleep(1 * rand(1, 5));
        for ($i = 0; $i < 10; $i++)
        {
            echo "Hello from thread #$t, i=$i" . PHP_EOL;
            sleep(1);
        }
        echo "Ending thread #$t" . PHP_EOL;
    
        return $t;
    };
```

### Basic Thread Usage

```php
// Create the Threads passing a callable
$thread1 = ByJG\PHPThread\Thread::create( $threadClousure );
$thread2 = ByJG\PHPThread\Thread::create( $threadClousure );

// Start the threads and passing parameters
$thread1->start(1);
$thread2->start(2);

// Wait the threads to finish
$thread1->join();
$thread2->join();

// Get the thread result
echo "Thread Result 1: " . $thread1->getResult();
echo "Thread Result 2: " . $thread2->getResult();
```

### Thread API

`Thread::create(Closure $closure, ?Closure $onFinish = null)` returns a `ByJG\PHPThread\Handler\ThreadInterface`
(`ForkHandler` or `ParallelHandler`). `$onFinish` is called inside the child process after the closure ends.
It is supported by the fork handler only: when the `parallel` extension is loaded, `Thread::create()` returns a
`ParallelHandler` and `$onFinish` is ignored (never called).

| Method                                              | Description                                                                      |
|-----------------------------------------------------|----------------------------------------------------------------------------------|
| `start(mixed ...$args)`                             | Start the thread, passing the arguments to the closure                           |
| `join()`                                            | Wait until the thread finishes                                                   |
| `getResult()`                                       | The closure return value. If the closure threw an exception, it is re-thrown here. It can be called again and returns (or re-throws) the same result |
| `isRunning()`                                       | `true` while the thread is running                                               |
| `getStatus()`                                       | A `ThreadStatus` enum: `NotStarted`, `Running`, `Finished` or `Error`            |
| `terminate(int $signal = SIGKILL, bool $wait = false)` | Stop the thread                                                               |

Call `Thread::gc()` at the end of the execution to remove the results left in the shared memory.
