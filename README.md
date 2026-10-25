---
sidebar_key: phpthread
sidebar_category: infrastructure
tags: [php, concurrency]
---

# PHPThread: Simplified Threads

Run PHP code in parallel with one API (threads, thread pools and JavaScript-like promises) that works on any PHP: real threads on ZTS, forked processes on the CLI, child processes under PHP-FPM.

[![Sponsor](https://img.shields.io/badge/Sponsor-%23ea4aaa?logo=githubsponsors&logoColor=white&labelColor=0d1117)](https://github.com/sponsors/byjg)
[![Build Status](https://github.com/byjg/php-phpthread/actions/workflows/phpunit.yml/badge.svg?branch=master)](https://github.com/byjg/php-phpthread/actions/workflows/phpunit.yml)
[![Opensource ByJG](https://img.shields.io/badge/opensource-byjg-success.svg)](https://opensource.byjg.com)
[![Install MCP Server](https://img.shields.io/badge/Install-MCP_Server-8A2BE2?logo=modelcontextprotocol&logoColor=white)](https://opensource.byjg.com/docs/ai/mcpserver-byjg-docs/)
[![GitHub source](https://img.shields.io/badge/Github-source-informational?logo=github)](https://github.com/byjg/php-phpthread/)
[![GitHub license](https://img.shields.io/github/license/byjg/php-phpthread.svg)](https://opensource.byjg.com/license/)
[![GitHub release](https://img.shields.io/github/release/byjg/php-phpthread.svg)](https://github.com/byjg/php-phpthread/releases/)

---

## Table of Contents

1. [Overview](#overview)
2. [Why Threads in PHP?](#why-threads-in-php)
3. [How It Works](#how-it-works)
   - [Default PHP (Non-ZTS)](#default-php-non-zts)
   - [PHP ZTS (Zend Thread Safety)](#php-zts-zend-thread-safety)
   - [Outside the CLI](#outside-the-cli)
4. [Features](#features)
5. [Limitations](#limitations)
6. [Installation](#installation)
7. [Running Tests](#running-tests)

---

## Overview

PHPThread is a polyfill library that abstracts threading functionality for PHP, providing a consistent
interface regardless of the underlying PHP setup (ZTS or Fork). It empowers developers to implement
thread-like behavior and asynchronous processing in PHP applications.

---

## Why Threads in PHP?

PHP is traditionally designed for a **request-response cycle**, where scripts are executed
only in response to client requests and terminate after sending a response. While efficient for
web applications, this architecture lacks native threading support for background or concurrent tasks.

The usual answers depend on how PHP was built: `parallel` needs a ZTS build, `pcntl_fork()`
only works on the CLI. Code written for one does not run on the other.

PHPThread hides that choice behind one API. The same `Thread`, `ThreadPool` and `Promise`
code runs on whichever is available:

```php
$thumbnails = Promise::map($images, fn($image) => makeThumbnail($image), concurrency: 4)->await();
```

It picks the backend for you:

- **Forking (Default PHP)** for simulating threading.
- **Zend Thread Safety (ZTS)** for true multi-threading environments.
- **Child PHP processes** (`proc_open`) outside the CLI, e.g. under PHP-FPM.

---

## How It Works

### Default PHP (Non-ZTS)

In standard PHP installations (without ZTS), threading is simulated using the `fork` command.
This approach creates a new process by cloning the parent process. While not a true thread,
this method can approximate threading behavior.

**Requirements:**

- [pcntl](https://www.php.net/manual/en/book.pcntl.php) and [posix](https://www.php.net/manual/en/book.posix.php): For process control.
  They are optional (`suggest` in `composer.json`): without them the `ProcessHandler` is used.
- A tmpfs mounted at `/dev/shm`: For sharing results between processes.

---

### PHP ZTS (Zend Thread Safety)

With ZTS-enabled PHP, true multi-threading becomes possible. This setup is ideal for production environments where
robust threading is required. The ZTS version is compiled with the `--enable-zts` flag, but it may not be included in
all PHP distributions.

**Requirements:**

- [parallel](https://www.php.net/manual/en/book.parallel.php): For multi-threading functionality.

---

### Outside the CLI

Forking and `parallel` only work in the CLI. Outside it, the `ProcessHandler` runs each closure in a child
PHP CLI process started with `proc_open()` (the approach of amphp/parallel). The closure is serialized with
[laravel/serializable-closure](https://github.com/laravel/serializable-closure), so it, its arguments and its result
must be serializable (no resources). See [ProcessHandler](docs/thread.md#processhandler).

**Requirements:**

- `proc_open()` enabled and a PHP CLI binary (found automatically, or set with `ProcessHandler::setPhpBinary()`).
- A tmpfs mounted at `/dev/shm`: used by promises.

---

## Features

- **Thread Management**: Simplified thread creation and execution ([docs](docs/thread.md)).
- **Thread Pools**: Efficiently manage and reuse threads for multiple tasks ([docs](docs/threadpool.md)).
- **Promises**: Truly asynchronous and non-blocking task management with a JavaScript-like Promise API ([docs](docs/promises.md), [benchmark](docs/promises-benchmark.md)).

Supported Promise Methods:

- `then()`: Execute a callback on promise resolution (and, optionally, on rejection).
- `catch()`: Execute a callback on promise rejection; the returned promise resolves with its return value.
- `finally()`: Execute a callback after resolution or rejection; the original value or reason is passed through.
- `Promise::resolve()`: Resolve a promise.
- `Promise::reject()`: Reject a promise.
- `Promise::all()`: Wait for all promises to resolve.
- `Promise::race()`: Wait for the first promise to resolve.

---

## Limitations

See also [Limitations](docs/limitations.md).

### Parallel Execution Outside the CLI

:::caution Important
In web server environments such as PHP-FPM, Apache with mod_php or FrankenPHP, `pcntl_fork()` and `parallel`
cannot be used. There `Thread::create()` returns a `ProcessHandler`: the closure runs in parallel in a **child PHP
CLI process**, so it must be serializable (see [ProcessHandler](docs/thread.md#processhandler) for the limits).

When `proc_open()` is disabled or no PHP CLI binary is found, it falls back to the `SyncHandler`: the closure runs
**synchronously** in the current process when `start()` is called. The code keeps working (threads, thread pools
and promises return the same results), but **there is no parallelism**: the work is done one task after another.
:::

To know which handler is used, call `Thread::handlerClass()` (the class `Thread::create()` returns in the current
environment) or `getClassName()` on the created thread:

```php
use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\Thread;

if (Thread::handlerClass() === SyncHandler::class) {
    // No parallelism here: ParallelHandler, ForkHandler or ProcessHandler are not available
}
```

`Thread::useHandler(SyncHandler::class)` forces the synchronous execution (e.g. in tests, or for a deterministic
run), and `Thread::useHandler(null)` restores the automatic choice.

**Why?**

- **pcntl extension** (used for forking): forking a web server worker would interfere with the server's own process management, so the library only forks in the CLI.
- **parallel extension** (used for true threading): requires a ZTS build and the CLI.
- **proc_open** (used by the `ProcessHandler`) starts a separate PHP CLI process, which is safe in any SAPI, at the cost of starting PHP (and the Composer autoloader) for each thread.

**Use Cases:**

This library is designed for:
- CLI scripts and daemons
- Background job processors
- Command-line tools
- Long-running CLI applications
- Scheduled tasks (cron jobs)
- Running a few independent tasks in parallel inside a web request

For long or many background tasks in web applications, consider using message queues (RabbitMQ, Redis, etc.) with background workers running in CLI mode.

### Forking and Data Sharing

When simulating threads with `fork`, data cannot be returned directly from the child process
to the parent. The library serializes the results to the tmpfs filesystem (`/dev/shm`) to share them between processes.

However:

- **Avoid returning large or complex objects**, as this may cause memory exhaustion.

---

## Installation

### Requirements

#### Non-ZTS (Default PHP)

- PHP ≥8.3
- `pcntl` and `posix` extensions (optional: without them the `ProcessHandler` runs the threads)
- tmpfs mounted at `/dev/shm`

#### Outside the CLI (PHP-FPM, ...)

- PHP ≥8.3
- `proc_open()` enabled and a PHP CLI binary installed next to the server one
- tmpfs mounted at `/dev/shm` (for promises)

#### ZTS (Zend Thread Safety)

- PHP ≥8.3 compiled with `--enable-zts`
- `parallel` extension
- `pcntl` and `posix` extensions (optional)

### Install via Composer

```bash
composer require byjg/phpthread
```

---

## Running Tests

```bash
docker compose up -d
vendor/bin/phpunit
```

----
[Open source ByJG](https://opensource.byjg.com)
