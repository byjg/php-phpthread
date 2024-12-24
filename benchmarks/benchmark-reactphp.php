<?php

require 'vendor/autoload.php';

$loop = React\EventLoop\Loop::get();
$promiseArray = [];

foreach (range(1, 10) as $i) {
    $promiseArray[$i] = new React\Promise\Promise(function ($resolve, $reject) use ($i, $loop) {
//        $loop->addTimer(11 - $i, function () use ($resolve, $i) {
        sleep(11 - $i);
        $resolve("Promise $i is fulfilled!");
    });

    $promiseArray[$i]->then(
        function ($value) {
            echo "Success: $value\n";
        },
    );
}

$loop->run();
