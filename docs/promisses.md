# Promisses

There is a very basic implementation of Promisses. 

It just implements `.then()` and it doesn't have any of the other features like chaining, return,  `.all()` , etc.

As is today, it is a "fancy" implementation of the Thread class. 

```php
// Create the Promisse
$promise = new \ByJG\PHPThread\Promisse(function ($resolve, $reject) {
    $num = rand(0, 10);
    //sleep(1);
    if ($num >= 5) {
        $resolve("Promise is fulfilled!");
    } else {
        $reject("Promise failed!");
    }
});

// After create the promisse, the promisse is executing in background
// And the status is pending
echo "A\n";
echo $promise->getPromisseStatus() . "\n";


// We can attach a callback to the promisse
// This implementation doesn't have any of the other fetures like chaining, return,  `.all()` , etc.
$promise
    ->then(
        function ($value) {
            echo "Success: $value\n";
        },
        function ($value) {
            echo "Failure: $value\n";
        }
    );

// The status is still pending
echo "B\n";
echo $promise->getPromisseStatus() . "\n";

// We wait for the promisse to finish and get the result
echo "C\n";
print_r($promise->await());

// Promisse now is fulfilled
echo "\nD\n";
echo $promise->getPromisseStatus() . "\n";

// I can call the promisse created before and run against another callback.
// It is not chainning, but allow to get the same result and run another callback
echo "E\n";
$promise
    ->then(
        function ($value) {
            echo "New Success: $value\n";
        },
        function ($value) {
            echo "New Failure: $value\n";
        }
    );
```

The result of the code above is:

```
A
pending
B
pending
C
Success: Promise is fulfilled!
Array
(
    [0] => Promise is fulfilled!
)

D
fulfilled
E
New Success: Promise is fulfilled!
```