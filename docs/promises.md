---
sidebar_position: 3
---

# Promises

This library provides an implementation of Promises that mimics the JavaScript Promise model.
The implementation is truly asynchronous and non-blocking, supporting multiple features including
`.then()`, `.catch()`, `.finally()`, `.all()`, `.race()`, `.resolve()`, and `.reject()`.

Following the JavaScript concept, when the promise is created, it is executed in background
and the status is pending.

It means the code will continue to run, and when the promise is fulfilled or rejected,
the callback will be executed.

It also has a method `await()` that will wait for the promise to be fulfilled or rejected.

A promise whose thread ends without settling it is rejected with a `RuntimeException`: the thread died (fatal error,
`exit`, killed) or the executor returned without calling `$resolve` or `$reject`. Unlike JavaScript, where such a
promise stays pending, nothing could settle it later, so `await()` and the chained promises would wait forever.
The process that created the promise detects it when it checks the promise (`await()`, `getStatus()`, or the
promises chained to it), and the chained promises reject with the same reason.

:::note
In PHP-FPM (or any other non-CLI SAPI), or without `pcntl` and `parallel`, each executor runs in a child PHP CLI
process (`ProcessHandler`), so the executors and the `then()`/`catch()`/`finally()` callbacks must be serializable
and two of them should not be written on the same line (see [ProcessHandler](thread.md#processhandler)).
Without `proc_open()` or a PHP CLI binary the promises still work but are not executed in background: the executor
runs synchronously when the promise is created, so the promise is already settled when `Promise::create()` returns.
See the `SyncHandler` in [Thread](thread.md#thread-api).
:::

## Promises Example:

```php
<?php

use ByJG\PHPThread\Promise;

$num = 7;

// Create the Promise
$promise = Promise::create(function ($resolve, $reject) use ($num) {
    sleep(3);
    if ($num >= 5) {
        $resolve("Promise is fulfilled!");
    } else {
        $reject("Promise failed!");
    }
});

// After create the promise, the promise is executing in background
// And the status is pending
// We can attach a callback to the promise. `then()` returns a new promise:
$then = $promise
    ->then(
        fn($resolve) => "Success: $resolve\n",
        fn($reject) => "Failure: $reject\n"
    );

// Show the status of the promise (a PromiseStatus enum):
echo $promise->getStatus()->value . "\n";

// We wait for the promise to finish and get the result
print_r($then->await());
```

The result of the code above is:

```
pending
Success: Promise is fulfilled!
```

## Promise Methods

### Then method

The `then` method is used to attach a callback to the promise. The first parameter is the callback to be executed when
the promise is resolved.

The second parameter is the callback to be executed when the promise is rejected.

```php
<?php    
$promise->then(
    fn($resolve) => "Success: $resolve\n",
    fn($reject) => "Failure: $reject\n"
);
```

`then()` returns a new promise, with the same rules as JavaScript:

- The promise is fulfilled: it resolves with the return value of the first callback.
- The promise is rejected and a second callback is given: the rejection is handled, and the new promise
  **resolves** with the return value of that callback. The next `then()` in the chain gets that value in its
  first callback.
- The promise is rejected and no second callback is given: the new promise rejects with the same reason.
- A callback throws an exception or a PHP `Error` (e.g. `TypeError`): the new promise rejects with it.
- A callback returns a promise: the new promise follows it, and settles with its value or reason.

```php
<?php
Promise::reject(1)
    ->then(fn($value) => $value + 2, fn($reason) => $reason + 5)   // handled: resolves with 6
    ->then(fn($value) => $value + 10, fn($reason) => $reason + 2)  // fulfilled: 6 + 10
    ->await();  // 16
```

### Returning a promise

As in JavaScript, a promise returned by a `then()`/`catch()` callback or by `Promise::try()`, or passed to `$resolve`
in an executor, is followed: the promise settles when that promise settles, with its value or reason.

```php
<?php
Promise::resolve(1)
    ->then(fn($id) => Promise::try(fn() => loadUser($id)))   // another task in background
    ->then(fn($user) => $user['name'])                       // gets the user, not the promise
    ->await();
```

A promise cannot be passed between threads, so the thread of the outer promise waits for the inner one and keeps
running (one more process or thread) until it settles. If the returned promise was created outside the callback and
captured by it, its thread dying is not detected from there: create the promise inside the callback.

### Catch method

The `catch` method is used to attach a callback to the promise. The callback will be executed when the promise is
rejected.

```php
<?php
$promise->catch(fn($reject) => "Failure: $reject\n");
```

`catch($onRejected)` is the same as `then(fn($value) => $value, $onRejected)`: a fulfilled promise passes its
value through, and a rejection handled by the callback **resolves** the returned promise with the callback's
return value. If the callback throws, the returned promise rejects with that exception.

```php
<?php
Promise::reject(1)
    ->catch(fn($reason) => $reason + 100)  // recovers: resolves with 101
    ->then(fn($value) => $value + 10)
    ->await();  // 111
```

### Finally method

The `finally` method is used to attach a callback to the promise. The callback will be executed when the promise is
resolved or rejected.

As in JavaScript, the callback receives **no argument** and its return value is ignored. The returned promise settles
with the original value or reason: a fulfilled promise stays fulfilled with the same value, and a rejected promise stays
rejected with the same reason. If the callback throws, the returned promise rejects with that exception.

```php
<?php
Promise::resolve(5)->finally(fn() => print("Done\n"))->await();  // prints "Done", returns 5
Promise::reject(7)->finally(fn() => 99)->await();               // still rejected, returns 7
```

## Promise Static Methods

### Try method

`Promise::try(Closure $closure, mixed ...$args)` runs the closure with the arguments in background: the promise is
fulfilled with its return value or rejected with what it throws, as `Promise.try()` in JavaScript. It is a shorter
way to write an executor that only resolves.

```php
<?php
$promise = Promise::try(fn(int $a, int $b) => $a + $b, 2, 3);
$promise->await();  // 5

// The same as
$promise = new Promise(function ($resolve) {
    $resolve(2 + 3);
});
```

The arguments and the return value must be serializable. A returned promise is followed (see
[Returning a promise](#returning-a-promise)).

### Resolve method

The `resolve` method is used to fulfill the promise. It will execute the callback attached to the promise.

```php
<?php
$promise = Promise::resolve(5);
$promise->then(fn($resolve) => $resolve + 2)->await()  // 7
```

### Reject method

The `reject` method is used to reject the promise. It will execute the callback attached to the promise.

```php
$promise = Promise::reject(6);
$promise->then(fn($resolve) => $resolve + 2, fn($reject) => $reject * 2)->await() // 12
```

### All method

The `all` method is used to wait for all promises to be fulfilled or rejected. The results follow the order of the
promises passed to it, not the order they settle.

```php
<?php
$promise1 = Promise::resolve(5);
$promise2 = Promise::resolve(6);

Promise::all($promise1, $promise2)->await();  // [5, 6]
```

### Map method

`Promise::map(iterable $items, Closure $callback, int $concurrency)` runs `$callback($item, $key)` for each item in a
thread, with at most `$concurrency` threads at a time: the next item starts when one settles. `Promise::all()` starts
every promise at once, so use `map()` for a long list, e.g. with one thread per CPU core:

```php
<?php
$thumbnails = Promise::map(
    $files,
    fn(string $file) => createThumbnail($file),   // runs in a thread
    4                                             // at most 4 threads at a time
)->await();   // in the order (and keys) of $files
```

The promise is fulfilled with the values in the order and keys of the items, or rejected with the first rejection,
after which no other item starts (the threads already running finish). A promise returned by the callback is followed.

The threads are managed by the thread of the returned promise (one more process). The items, the callback and the
values must be serializable, so the items are read at once: a generator is converted to an array.

### Race method

The `race` method is used to wait for the first promise to be fulfilled or rejected.

```php
<?php
$promise1 = Promise::resolve(5);
$promise2 = Promise::resolve(6);

Promise::race($promise1, $promise2)->await();  // 5
```
