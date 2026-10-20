---
sidebar_position: 5
---

# Limitations

## PHP-FPM

`pcntl_fork()` and the `parallel` extension are only used in the CLI. In PHP-FPM (nginx or apache), FrankenPHP or
any other non-CLI SAPI, `Thread::create()` returns a `ProcessHandler`, which runs the closure in a child PHP CLI
process: threads run in parallel, with the limits below.

When `proc_open()` is disabled or no PHP CLI binary is found, `Thread::create()` returns a `SyncHandler`, which runs
the closure synchronously in the current process: threads, thread pools and promises keep working and return the
same results, but one task after another (no parallelism). `Thread::handlerClass()` tells which handler is used.

## ProcessHandler

- The closure, its arguments, the objects it captures and its return value must be serializable (no resources).
- The child process only has what the Composer autoloader loads: no application bootstrap, globals or functions
  declared outside the autoloader; changes to captured variables are not seen by the parent.
- Two closures on the same line with the same number of parameters and captured variables can be mixed up when they
  are serialized: write them on separate lines.
- The child uses the CLI `php.ini`, and starting it costs some milliseconds per thread.
- It needs `proc_open()` and a PHP CLI binary (`ProcessHandler::setPhpBinary()`), and the promises need `/dev/shm`.

See [ProcessHandler](thread.md#processhandler).

## Forking and Data Sharing between Threads

The only way to share data between threads is by using a shared memory segment.
PHP Threads uses the tmpfs filesystem to create a shared memory segment.
This filesystem is a memory-based filesystem, so it is very fast.

There are some caveats when using shared memory:

- Not all systems supports tmpfs. If your system does not support tmpfs, the thread returns will not work.
- Avoid using large data in the tmpfs. It can slow down your system since you have to serialize and unserializa the data
  you are sharing.
- If you are using thread in threads or Promises, there will be some files left in the tmpfs filesystem.
  You can remove them by calling the `Thread::gc()` or `Promise::gc()` method by the end of the execution.

## Promises won't work with PHP ZTS

So far, the Promises won't work with PHP ZTS. 
