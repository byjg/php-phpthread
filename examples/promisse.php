<?php


use ByJG\PHPThread\Promise;
use ByJG\PHPThread\SharedMemory;

require_once __DIR__ . '/../vendor/autoload.php';

SharedMemory::getInstance()->clear();

// Create the Promise
$promise = new Promise(function ($resolve, $reject) {
    $num = rand(0, 10);
//    sleep(1);
    if ($num >= 5) {
        $resolve("Promise is fulfilled!");
    } else {
        $reject("Promise failed!");
    }
});

// After create the promise, the promise is executing in background
// And the status is pending
echo "A\n";
echo $promise->getStatus()->value . "\n";


// We can attach a callback to the promise
// This implementation doesn't have any of the other fetures like chaining, return,  `.all()` , etc.
$promise
    ->then(
        fn($value) => "Success: $value\n",
        fn($value) => "Failure: $value\n"
    );

// The status is still pending
echo "B\n";
echo $promise->getStatus()->value . "\n";

// We wait for the promise to finish and get the result
echo "C\n";
print_r($promise->await());

// Promise now is fulfilled
echo "\nD\n";
echo $promise->getStatus()->value . "\n";

// I can call the promise create and run against another callback.
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

