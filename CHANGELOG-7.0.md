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

## Requirements

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

## Housekeeping

- `phpunit.xml.dist` renamed to `phpunit.xml`.
