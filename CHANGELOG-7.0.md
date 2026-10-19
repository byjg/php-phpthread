# Changelog - Version 7.0

> **Status: in development.** This document tracks changes landing on the `7.0` branch.
> Nothing here is released yet, and the contents may still change.

## Breaking Changes

### Enum cases renamed to PascalCase

Enum cases follow the ByJG standard and are written in PascalCase. The backed values of
`PromiseStatus` did not change, so `->value`, `from()`, `tryFrom()` and stored data are not
affected; only code that names a case (or reads `->name`) has to be updated. `ThreadStatus`
is a pure (non-backed) enum, so any code that names its cases has to be updated.

| Enum | Before (6.x) | After (7.0) |
|---|---|---|
| `PromiseStatus` | `pending`, `fulfilled`, `rejected` | `Pending`, `Fulfilled`, `Rejected` |
| `ThreadStatus` | `notStarted`, `running`, `finished`, `error` | `NotStarted`, `Running`, `Finished`, `Error` |

### ForkHandler keeps its state after getResult()

`ForkHandler::getStatus()` returns `Finished` after `getResult()`, not `NotStarted`, and a second
`getResult()` returns (or re-throws) the result instead of `null` (see Bug Fixes).

### Promise then/catch/finally follow JavaScript

- A rejection handled by `then($onFulfilled, $onRejected)` or `catch($onRejected)` now **resolves** the
  returned promise with the handler's return value, so the chain continues on the fulfilled path. Before,
  the returned promise was rejected again with that value: `Promise::reject(1)->then(fn($v) => $v + 2,
  fn($r) => $r + 5)->then(fn($v) => $v + 10, fn($r) => $r + 2)` gave `8`, it gives `16` now. To keep a
  promise rejected from a handler, throw an exception in it.
- `finally($onFinally)` calls the callback with no argument and ignores its return value; the returned
  promise settles with the original value or reason. Before, the callback received the value (or reason)
  and the returned promise resolved with whatever it returned. A callback declared with a required
  parameter (`fn($value) => ...`) now fails, since it is called with no argument; declare it without one.

### Thread::create() no longer throws outside the CLI

`Thread::create()` (and so `Promise` and `ThreadPool`) threw a `RuntimeException` ("Threads only works in CLI
mode", or "PHP need to be compiled with ZTS extension or compiled with the --enable-pcntl") under PHP-FPM or any
other non-CLI SAPI, or without `pcntl_fork()` and `parallel`. It now returns a `ProcessHandler`, which runs the
closure in a child PHP CLI process, or, without `proc_open()` or a PHP CLI binary, a `SyncHandler` that runs the
closure synchronously (see New Features). Code that caught that exception to detect an unsupported environment should
check `Thread::handlerClass()` instead. `ForkHandler` and `ParallelHandler` still throw when instantiated directly
outside the CLI.

### Promise::all() keeps the order of the promises

`Promise::all()` resolves with the results in the order of the promises passed to it, as in JavaScript. Before,
they were in the order the promises settled (see Bug Fixes).

### A promise whose thread ends without settling it is rejected

An executor that returns without calling `$resolve` or `$reject` now rejects the promise with a `RuntimeException`
("The thread ended without settling the promise ..."), instead of leaving it pending (see Bug Fixes).

## New Features

### Promise::try()

- `Promise::try(Closure $closure, mixed ...$args)` runs the closure in a thread and returns a promise fulfilled with
  its return value or rejected with what it throws, as `Promise.try()` in JavaScript. See `docs/promises.md`.

### Promise::map()

- `Promise::map(iterable $items, Closure $callback, int $concurrency)` runs the callback for each item in a thread,
  at most `$concurrency` at a time, and resolves with the values in the order of the items (or rejects with the first
  rejection). `Promise::all()` starts every promise at once. See `docs/promises.md`.

### A returned promise is followed

- A promise returned by a `then()`/`catch()` callback or by `Promise::try()`, or passed to `$resolve`, is followed as
  in JavaScript: the promise settles with its value or reason. The thread waits for the inner promise, since a
  promise cannot be shared between threads. Before, the promise could not be stored in the shared memory and the
  outer promise ended rejected ("The thread ended without settling the promise"). See `docs/promises.md`
  (Returning a promise).

### SyncHandler: synchronous fallback

- New `ByJG\PHPThread\Handler\SyncHandler`: runs the closure in the current process when `start()` is called
  and keeps its result, or the exception it threw, which `getResult()` re-throws on every call (as
  `ForkHandler`). `isRunning()` is always `false`, `join()` and `terminate()` do nothing, `getStatus()` is
  `NotStarted` before `start()` and `Finished` after it, and `$onFinish` is called after the closure.
- `Thread::create()` picks the handler automatically: `ParallelHandler` in the CLI with the `parallel` extension,
  `ForkHandler` in the CLI with `pcntl_fork()`, `SyncHandler` otherwise. Threads, thread pools and promises
  therefore work under PHP-FPM, without parallelism.
- New `Thread::handlerClass()` returns the handler class `Thread::create()` uses.
- New `Thread::useHandler(?string $handlerClass)` forces the handler `Thread::create()` uses (`ParallelHandler`,
  `ForkHandler` or `SyncHandler`; any other class throws `InvalidArgumentException`), e.g. `SyncHandler` in tests
  or for a deterministic run; `null` restores the automatic choice. A forced `ForkHandler` or `ParallelHandler`
  still throws when it cannot run. The promise and thread pool tests run with both `ForkHandler` and `SyncHandler`.

### ProcessHandler: parallel threads outside the CLI

- New `ByJG\PHPThread\Handler\ProcessHandler`: runs the closure in a child PHP CLI process started with
  `proc_open()` (the approach of amphp/parallel), so threads, thread pools and promises run in parallel under
  PHP-FPM, FrankenPHP or any other SAPI. The closure, its arguments and `$onFinish` are serialized with
  `laravel/serializable-closure`; the child (`src/Handler/process-worker.php`) loads the project's Composer
  autoloader, runs the closure and `$onFinish`, and sends back the result or the exception, which `getResult()`
  re-throws (as `ForkHandler`). `getResult()` waits for the child. `terminate()` uses `proc_terminate()`.
- The closure, its arguments, what it captures and its result must be serializable (no resources), the child does
  not run the application bootstrap, and two closures on the same line can be mixed up by the serializer.
  See `docs/thread.md` (ProcessHandler) and `docs/limitations.md`.
- `ProcessHandler::setPhpBinary(?string)` sets the PHP CLI binary (default: `PHP_BINARY` in the CLI, otherwise
  `PHP_BINDIR/php` or `php` in the `PATH`); `getPhpBinary()` and `isAvailable()` tell what is used.
- `Thread::create()` picks the `ProcessHandler` when `ParallelHandler` and `ForkHandler` cannot be used (outside the
  CLI, or a CLI without `parallel`/`pcntl`) and `proc_open()` and a PHP CLI binary are available; the
  `SyncHandler` is now the last fallback. `Thread::useHandler()` accepts `ProcessHandler::class`.
- `SharedMemory::getPrefix()` and `SharedMemory::usePrefix()` (`@internal`) let the child process share the
  parent's shared memory (used by the promises).
- The promise and thread pool tests also run with the `ProcessHandler`.

## Bug Fixes

- `then()` with an `$onRejected` handler and `catch()` rejected the returned promise with the handler's
  return value instead of resolving it, so a handled rejection could not be recovered from; and
  `finally()` replaced the promise's value or reason with the callback's return value, and turned a
  rejected promise into a fulfilled one. Both now follow the JavaScript Promise semantics (see Breaking
  Changes). A handler or `finally` callback that throws rejects the returned promise with that exception.

- `ForkHandler::getResult()` cleared the thread key, so the thread looked as if it had never been
  started: `getStatus()` returned `NotStarted` and every later `getResult()` returned `null`. The
  result is now read from the shared memory once and kept; `start()` clears it for the next run.

- A PHP `Error` (`TypeError`, `ArgumentCountError`, a call on a non-object, ...) thrown by an executor or
  a callback was not caught, so the promise never settled: `await()` threw "Promise result is null" and
  anything chained after it never returned. It now rejects the promise, like an exception. A `finally()`
  callback still written as `fn($value) => ...` gets no argument and rejects with `ArgumentCountError`.

- `Promise::all()` resolved with the results in the order the promises settled, not the order they were passed
  in, so `Promise::all($slow, $fast)` gave `[fast, slow]` with the `ForkHandler`. The results now follow the order
  of the promises, as in JavaScript.

- A promise whose thread died (fatal error, `exit`, killed) or whose executor returned without settling it stayed
  pending forever: `await()` threw "Promise result is null" and the promises chained to it (`then`, `catch`,
  `finally`, `all`, `race`), which wait in other processes, never returned. The process that created the promise
  now rejects it with a `RuntimeException` (the `ProcessHandler` gives the exit code) and shares the rejection
  with the chained promises, which reject with the same reason.

## Requirements

- `ext-pcntl` and `ext-posix` moved from `require` to `suggest`: they are only needed by the `ForkHandler`, which
  checks that both are loaded (`ForkHandler::isAvailable()`). Without them `Thread::create()` uses the
  `ProcessHandler` in the CLI too. `src/polyfill.php` (Composer `files` autoload) defines `SIGKILL` and `SIGTERM`
  when `pcntl` is not loaded.

- New dependency `laravel/serializable-closure` `^2.1` (requires `php ^8.1`), used by the `ProcessHandler` to send
  the closures to the child process.

- PHP 8.3, 8.4, 8.5 and 8.6 are now supported: `"php": ">=8.3 <8.7"`.
  The previous `<8.6` upper bound excluded PHP 8.6, since `<8.6` is exclusive.

### ByJG dependencies

- `byjg/cache-engine` is now `^7.0`.
- `byjg/restserver` is now `^7.0`.

While 7.0 is unreleased these resolve to `7.0.x-dev` from each component's
`7.0` branch, via `minimum-stability: dev` with `prefer-stable: true`.

## Toolchain

- PHPUnit updated to `^12.5`.
- Psalm is installed as `psalm/phar` instead of `vimeo/psalm`.

  `vimeo/psalm` lists the PHP versions it supports and no published release includes
  8.6, so as a dev dependency it made `composer install` fail on the 8.6 build job
  before any test ran. `psalm/phar` requires only `php ^8.2` and bundles its own
  dependencies, so it installs on every PHP version in the matrix and cannot conflict
  with the project's. Psalm itself still refuses to *run* on 8.6, which is why the
  Psalm job uses 8.5. `composer psalm` runs it.

- PHPUnit 13 is deliberately **not** used. It requires PHP `>=8.4.1`, breaking the
  8.3 floor.

## Continuous Integration

- The build matrix now includes PHP 8.6.
- The Psalm job runs on PHP 8.5.
- The PHP-FPM check expects the `/thread`, `/threadpool` and `/promise` endpoints to return their results
  (computed by the `ProcessHandler`, in parallel) instead of the "Threads only works in CLI mode" error, and the
  new `/handler` endpoint to report the `ProcessHandler`.

## Housekeeping

- The test suite runs faster: the sleeps are tenths of a second instead of seconds, and the timing-dependent
  scenarios (race order, parallelism) run only with the parallel handlers. PHPUnit enforces a 20 s limit per test
  (`enforceTimeLimit`, `failOnRisky`), so a test that would hang fails instead.

- `phpunit.xml.dist` renamed to `phpunit.xml`.
