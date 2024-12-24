<?php

use ByJG\PHPThread\Promise;

require 'vendor/autoload.php';

$promiseArray = [];

foreach (range(1, 10) as $i) {
    // Here the promisse is created and start executing in background.
    $promiseArray[$i] = Promise::create(function ($resolve, $reject) use ($i) {
        sleep(11 - $i);
        $resolve("Promise $i is fulfilled!");
    })
        ->then(
            function ($value) {
                echo "Success: $value\n";
            }
        );
    echo "Promise $i {$promiseArray[$i]->getPromiseId()} is created!\n";
}

Promise::all(...$promiseArray)->await();

Promise::gc();

