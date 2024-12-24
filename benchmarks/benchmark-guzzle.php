<?php

use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils;

require 'vendor/autoload.php';

$promiseArray = [];

foreach (range(1, 10) as $i) {
    $promiseArray[$i] = new Promise(function () use ($i, &$promiseArray) {
        sleep(11 - $i);
        $promiseArray[$i]->resolve("Promise $i is fulfilled!");
    });

    $promiseArray[$i]
        ->then(
            function ($value) {
                echo "Success: $value\n";
            }
        );
    echo "Promise $i is created!\n";
}

Utils::all($promiseArray)->wait();

