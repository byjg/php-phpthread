# Promises

There is a very basic implementation of Promises. It tries to mimic the JavaScript Promises,
however it just implements `.then()` and it doesn't have any of the other features like chaining, return,  `.all()` ,
etc.

Following the JavaScript concept, when the promise is created, it is executed in background
and the status is pending.

It means the code will continue to run, and when the promise is fulfilled or rejected,
the callback will be executed.

It also has a method `await()` that will wait for the promise to be fulfilled or rejected.

## Promises Example:

```php
<?php

use ByJG\PHPThread\Promise;

// Create the Promise
$promise = Promise::create(function ($resolve, $reject) {
    sleep(3);
    if ($num >= 5) {
        $resolve("Promise is fulfilled!");
    } else {
        $reject("Promise failed!");
    }
});

// After create the promise, the promise is executing in background
// And the status is pending
// We can attach a callback to the promise:
$promise
    ->then(
        fn($resolve) => "Success: $resolve\n",
        fn($reject) => "Failure: $reject\n"
    );

// Show the status of the promise:
echo $promise->getStatus() . "\n";

// We wait for the promise to finish and get the result
print_r($promise->await());
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

### Catch method

The `catch` method is used to attach a callback to the promise. The callback will be executed when the promise is
rejected.

```php
<?php
$promise->catch(fn($reject) => "Failure: $reject\n");
```

### Finally method

The `finally` method is used to attach a callback to the promise. The callback will be executed when the promise is
resolved or rejected.

```php
<?php
$promise->finally(fn($result) => "Finally: $result\n");
```

## Promise Static Methods

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

The `all` method is used to wait for all promises to be fulfilled or rejected.

```php
<?php
$promise1 = Promise::resolve(5);
$promise2 = Promise::resolve(6);

Promise::all($promise1, $promise2)->await();  // [5, 6]
```

### Race method

The `race` method is used to wait for the first promise to be fulfilled or rejected.

```php
<?php
$promise1 = Promise::resolve(5);
$promise2 = Promise::resolve(6);

Promise::race($promise1, $promise2)->await();  // 5
```
