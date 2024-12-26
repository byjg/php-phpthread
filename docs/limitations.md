# Limitations

## PHP-FPM

PHP Threads is not supported in a PHP-FPM environment (nginx or apache).

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
