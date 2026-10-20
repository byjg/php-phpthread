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
| `ForkHandler`     | CLI and the `pcntl` and `posix` extensions are loaded             | yes      |
| `ProcessHandler`  | Otherwise (PHP-FPM or any other SAPI, or no `pcntl`/`parallel`), when `proc_open()` is enabled and a PHP CLI binary is found | yes |
| `SyncHandler`     | Otherwise                                                         | no       |

`Thread::useHandler()` (below) overrides this choice.

With the `SyncHandler` the closure runs synchronously in the current process when `start()` is called: `start()`
returns only after the closure ends, `isRunning()` is always `false`, `join()` and `terminate()` do nothing, and
`getStatus()` is `Finished`. The result (or the re-thrown exception) is the same as with the other handlers.

`Thread::handlerClass()` returns the handler class `Thread::create()` uses, and `getClassName()` returns the
class of a created thread.

`Thread::useHandler(?string $handlerClass)` forces the handler `Thread::create()` uses (and so `ThreadPool` and
`Promise`): `ParallelHandler::class`, `ForkHandler::class`, `ProcessHandler::class` or `SyncHandler::class`; any other class
throws an `InvalidArgumentException`. `null` restores the automatic choice. A forced `ForkHandler`, `ParallelHandler`
or `ProcessHandler` still throws when it cannot run (outside the CLI, without `pcntl`/`parallel`, or without
`proc_open()`/a PHP CLI binary). Forcing the `SyncHandler` is
useful in tests or for a deterministic run, since everything runs one after another in the current process:

```php
use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\Thread;

Thread::useHandler(SyncHandler::class);
// ... Thread::create(), ThreadPool and Promise run synchronously here
Thread::useHandler(null);
```

`$onFinish` is called with no argument after the closure ends (also when it throws): inside the child process
with the `ForkHandler` and the `ProcessHandler`, in the current process with the `SyncHandler`. When the `parallel` extension is loaded,
`Thread::create()` returns a `ParallelHandler` and `$onFinish` is ignored (never called).

| Method                                              | Description                                                                      |
|-----------------------------------------------------|----------------------------------------------------------------------------------|
| `start(mixed ...$args)`                             | Start the thread, passing the arguments to the closure                           |
| `join()`                                            | Wait until the thread finishes                                                   |
| `getResult()`                                       | The closure return value. If the closure threw an exception, it is re-thrown here. It can be called again and returns (or re-throws) the same result |
| `isRunning()`                                       | `true` while the thread is running                                               |
| `getStatus()`                                       | A `ThreadStatus` enum: `NotStarted`, `Running`, `Finished` or `Error`            |
| `terminate(int $signal = SIGKILL, bool $wait = false)` | Stop the thread                                                               |

The `pcntl` and `posix` extensions are optional. Without `pcntl`, the library defines the `SIGKILL` (9) and `SIGTERM`
(15) constants, so `terminate()` keeps its default and the signals can be passed to the `ProcessHandler`.

Call `Thread::gc()` at the end of the execution to remove the results left in the shared memory.

### ProcessHandler

The `ProcessHandler` runs the closure in a child PHP CLI process started with `proc_open()`, so threads run in
parallel outside the CLI too (PHP-FPM, Apache mod_php, FrankenPHP, ...):

1. `start()` serializes the closure, its arguments and `$onFinish` with
   [laravel/serializable-closure](https://github.com/laravel/serializable-closure) and sends them to the child
   (`src/Handler/process-worker.php`), which loads the project's Composer autoloader (`vendor/autoload.php`).
2. The child runs the closure, then `$onFinish`, and sends back the serialized return value, or the exception it threw.
3. `isRunning()`, `getStatus()` and `join()` read the result while the child runs, so a large result never blocks it.
   `getResult()` waits for the child (it calls `join()`) and returns the value or re-throws the exception
   (same class, message and code).
4. `terminate($signal, $wait)` sends the signal to the child (`proc_terminate()`). A terminated thread has no
   result (`null`).

The PHP CLI binary is `PHP_BINARY` in the CLI. Outside it `PHP_BINARY` is the server binary (e.g. `php-fpm`), so
`PHP_BINDIR/php` is used, or `php` in the `PATH`. Set it explicitly when it is somewhere else:

```php
use ByJG\PHPThread\Handler\ProcessHandler;

ProcessHandler::setPhpBinary('/usr/bin/php8.4'); // throws InvalidArgumentException if it is not executable
ProcessHandler::setPhpBinary(null);              // back to the automatic choice
ProcessHandler::getPhpBinary();                  // the binary used, or null if none was found
ProcessHandler::isAvailable();                   // proc_open() enabled and a binary found
```

Limits (see also [Limitations](limitations.md#processhandler)):

- The closure, its arguments, the objects it captures (`use`, `$this` when the closure uses it) and its return value
  must be serializable: no resources (files, sockets, database connections), no `Closure` in the arguments or the result.
- The child is a fresh PHP process: it only has what the Composer autoloader loads. Functions or classes declared
  outside it, globals, constants defined at runtime and the application bootstrap (container, configuration, ...)
  are not there. Changes the closure makes to captured variables or objects are not seen by the parent.
- The child uses the CLI `php.ini` (e.g. its `memory_limit` and no `max_execution_time`), not the FPM one.
- The closures are serialized from their source code: two closures on the **same line** with the same number of
  parameters and captured variables cannot be told apart, and the child may run the wrong one. Write them on
  separate lines, e.g. `->then(fn($v) => ...,` and `fn($r) => ...)` on the next line.
- Starting PHP and the autoloader costs some milliseconds per thread: prefer a few long tasks over many short ones.
- What the closure prints goes to the parent's STDOUT/STDERR (under PHP-FPM, usually the FPM log or nowhere), not
  to the HTTP response.
- Call `join()` or `getResult()` for every started thread; a process that is never waited for stays as a zombie
  until the parent process ends.
