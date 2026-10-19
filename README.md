---
sidebar_key: phpthread
tags: [php, concurrency]
---

# ByJG PHPThread: Simplified Threads and Non-Blocking Code in PHP

ByJG PHPThread simplifies working with threads and non-blocking code in PHP,
bridging the gap for a language that was not inherently designed for threading.

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

With PHPThread, you can overcome these limitations by leveraging:

- **Forking (Default PHP)** for simulating threading.
- **Zend Thread Safety (ZTS)** for true multi-threading environments.

---

## How It Works

### Default PHP (Non-ZTS)

In standard PHP installations (without ZTS), threading is simulated using the `fork` command.
This approach creates a new process by cloning the parent process. While not a true thread,
this method can approximate threading behavior.

**Requirements:**

- [pcntl](https://www.php.net/manual/en/book.pcntl.php) and [posix](https://www.php.net/manual/en/book.posix.php): For process control.
- A tmpfs mounted at `/dev/shm`: For sharing results between processes.

---

### PHP ZTS (Zend Thread Safety)

With ZTS-enabled PHP, true multi-threading becomes possible. This setup is ideal for production environments where
robust threading is required. The ZTS version is compiled with the `--enable-zts` flag, but it may not be included in
all PHP distributions.

**Requirements:**

- [parallel](https://www.php.net/manual/en/book.parallel.php): For multi-threading functionality.

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

### Parallel Execution in CLI Mode Only

:::caution Important
Threads **only run in parallel in CLI mode** (command-line interface). In web server environments such as:
- PHP-FPM
- Apache with mod_php
- Any other web server SAPI

or when neither `pcntl_fork()` nor the `parallel` extension is available, `Thread::create()` falls back to the
`SyncHandler`: the closure runs **synchronously** in the current process when `start()` is called. The code keeps
working (threads, thread pools and promises return the same results), but **there is no parallelism**: the work is
done one task after another.
:::

To know which handler is used, call `Thread::handlerClass()` (the class `Thread::create()` returns in the current
environment) or `getClassName()` on the created thread:

```php
use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\Thread;

if (Thread::handlerClass() === SyncHandler::class) {
    // No parallelism here: ForkHandler or ParallelHandler are not available
}
```

`Thread::useHandler(SyncHandler::class)` forces the synchronous execution (e.g. in tests, or for a deterministic
run), and `Thread::useHandler(null)` restores the automatic choice.

**Why?**

Both threading approaches used by this library require direct process control, which is unavailable in web server environments:

- **pcntl extension** (used for forking): Disabled in web server contexts for security and stability reasons. The `pcntl_fork()` function and related process control functions only work in CLI mode.
- **parallel extension** (used for true threading): Also requires CLI mode and is not available in web server SAPIs.

Additionally, forking or creating threads within a web server process would interfere with the server's own process/thread management, potentially causing instability.

**Use Cases:**

This library is designed for:
- CLI scripts and daemons
- Background job processors
- Command-line tools
- Long-running CLI applications
- Scheduled tasks (cron jobs)

For web applications requiring real asynchronous processing, consider using message queues (RabbitMQ, Redis, etc.) with background workers running in CLI mode.

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
- `pcntl` and `posix` extensions
- tmpfs mounted at `/dev/shm`

#### ZTS (Zend Thread Safety)

- PHP ≥8.3 compiled with `--enable-zts`
- `parallel` extension
- `pcntl` and `posix` extensions (required by `composer.json`)

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
