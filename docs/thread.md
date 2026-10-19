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

`Thread::create(Closure $closure, ?Closure $onFinish = null)` returns a `ByJG\PHPThread\Handler\ThreadInterface`.
The handler depends on the environment:

| Handler           | When                                                              | Parallel |
|-------------------|-------------------------------------------------------------------|----------|
| `ParallelHandler` | CLI and the `parallel` extension is loaded (PHP ZTS)              | yes      |
| `ForkHandler`     | CLI and `pcntl_fork()` is available                               | yes      |
| `SyncHandler`     | Otherwise: PHP-FPM or any other web SAPI, or no `pcntl`/`parallel` | no       |

`Thread::useHandler()` (below) overrides this choice.

With the `SyncHandler` the closure runs synchronously in the current process when `start()` is called: `start()`
returns only after the closure ends, `isRunning()` is always `false`, `join()` and `terminate()` do nothing, and
`getStatus()` is `Finished`. The result (or the re-thrown exception) is the same as with the other handlers.

`Thread::handlerClass()` returns the handler class `Thread::create()` uses, and `getClassName()` returns the
class of a created thread.

`Thread::useHandler(?string $handlerClass)` forces the handler `Thread::create()` uses (and so `ThreadPool` and
`Promise`): `ParallelHandler::class`, `ForkHandler::class` or `SyncHandler::class`; any other class throws an
`InvalidArgumentException`. `null` restores the automatic choice. A forced `ForkHandler` or `ParallelHandler`
still throws when it cannot run (outside the CLI, or without `pcntl`/`parallel`). Forcing the `SyncHandler` is
useful in tests or for a deterministic run, since everything runs one after another in the current process:

```php
use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\Thread;

Thread::useHandler(SyncHandler::class);
// ... Thread::create(), ThreadPool and Promise run synchronously here
Thread::useHandler(null);
```

`$onFinish` is called with no argument after the closure ends (also when it throws): inside the child process
with the `ForkHandler`, in the current process with the `SyncHandler`. When the `parallel` extension is loaded,
`Thread::create()` returns a `ParallelHandler` and `$onFinish` is ignored (never called).

| Method                                              | Description                                                                      |
|-----------------------------------------------------|----------------------------------------------------------------------------------|
| `start(mixed ...$args)`                             | Start the thread, passing the arguments to the closure                           |
| `join()`                                            | Wait until the thread finishes                                                   |
| `getResult()`                                       | The closure return value. If the closure threw an exception, it is re-thrown here. It can be called again and returns (or re-throws) the same result |
| `isRunning()`                                       | `true` while the thread is running                                               |
| `getStatus()`                                       | A `ThreadStatus` enum: `NotStarted`, `Running`, `Finished` or `Error`            |
| `terminate(int $signal = SIGKILL, bool $wait = false)` | Stop the thread                                                               |

Call `Thread::gc()` at the end of the execution to remove the results left in the shared memory.
