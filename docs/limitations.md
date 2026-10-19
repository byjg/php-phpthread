---
sidebar_position: 5
---

# Limitations

## PHP-FPM

Threads do not run in parallel in a PHP-FPM environment (nginx or apache) or any other non-CLI SAPI, nor when
neither `pcntl_fork()` nor the `parallel` extension is available. In that case `Thread::create()` returns a
`SyncHandler`, which runs the closure synchronously in the current process: threads, thread pools and promises
keep working and return the same results, but one task after another (no parallelism).
`Thread::handlerClass()` tells which handler is used.

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
