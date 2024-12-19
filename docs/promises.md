# Promises

There is a very basic implementation of Promises. It tries to mimic the JavaScript Promises,
however it just implements `.then()` and it doesn't have any of the other features like chaining, return,  `.all()` ,
etc.

Following the JavaScript concept, when the promise is created, it is executed in background
and the status is pending.

It means the code will continue to run, and when the promise is fulfilled or rejected, the callback will be executed.

It also has a method `await()` that will wait for the promise to be fulfilled or rejected.

```php
// Create the Promise
$promise = new \ByJG\PHPThread\Promise(function ($resolve, $reject) {
    $num = rand(0, 10);
    //sleep(1);
    if ($num >= 5) {
        $resolve("Promise is fulfilled!");
    } else {
        $reject("Promise failed!");
    }
});

// After create the promise, the promise is executing in background
// And the status is pending
echo "A\n";
echo $promise->getPromiseStatus() . "\n";


// We can attach a callback to the promise
// This implementation doesn't have any of the other fetures like chaining, return,  `.all()` , etc.
$promise
    ->then(
        fn($value) => "Success: $value\n",
        fn($value) => "Failure: $value\n"
    );

// The status is still pending
echo "B\n";
echo $promise->getPromiseStatus() . "\n";

// We wait for the promise to finish and get the result
echo "C\n";
print_r($promise->await());

// Promise now is fulfilled
echo "\nD\n";
echo $promise->getPromiseStatus() . "\n";

// I can call the promise created before and run against another callback.
// It is not chainning, but allow to get the same result and run another callback
echo "E\n";
$promise
    ->then(
        fn($value) => "New Success: $value\n",
        fn($value) => "New Failure: $value\n"
    );
```

The result of the code above is:

```
A
pending
B
pending
C
Array
(
    [0] => Promise is fulfilled!
)

D
fulfilled
E
New Success: Promise is fulfilled!
```