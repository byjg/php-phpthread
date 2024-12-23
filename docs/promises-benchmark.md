# Promise Benchmark

Let's compare the ByJG PHPThread Promise with Guzzle Promise and ReactPHP Promise.

## The code

### ByJG PHPThread Promise

```php
<?php
use ByJG\PHPThread\Promise;

$promiseArray = [];

foreach (range(1, 10) as $i) {
    $promiseArray[$i] = new Promise(function ($resolve, $reject) use ($i) {
        sleep(3);
        $resolve("Promise $i is fulfilled!");
    });

    $promiseArray[$i]->then(
        function ($value) {
            echo "Success: $value\n";
        },
    );
}

foreach ($promiseArray as $promise) {
    echo $promise->getPromiseStatus()->value . "\n";
}

foreach ($promiseArray as $promise) {
    $promise->await();
}
```

### ReactPHP Promise

```php
<?php
$loop = React\EventLoop\Loop::get();
$promiseArray = [];

foreach (range(1, 10) as $i) {
    $promiseArray[$i] = new React\Promise\Promise(function ($resolve, $reject) use ($i, $loop) {
        $loop->addTimer(3, function () use ($resolve, $i) {
            $resolve("Promise $i is fulfilled!");
        });
    });

    $promiseArray[$i]->then(
        function ($value) {
            echo "Success: $value\n";
        },
    );
}

$loop->run();
```

### GuzzleHttp Promise

```php
<?php
use GuzzleHttp\Promise;

$promiseArray = [];

foreach (range(1, 10) as $i) {
    $promiseArray[$i] = new Promise(function ($resolve, $reject) use ($i) {
        sleep(3);
        $resolve("Promise $i is fulfilled!");
    });

    $promiseArray[$i]->then(
        function ($value) {
            echo "Success: $value\n";
        },
    );
}

foreach ($promiseArray as $promise) {
    echo $promise->getState() . "\n";
}

Promise\all($promiseArray)->wait();
```
