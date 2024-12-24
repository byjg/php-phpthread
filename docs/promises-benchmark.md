# Promise Benchmark

The `ByJG PHPThread Promise` implements Promises in PHP, adhering to the JavaScript Promise model.
This means the Promise is truly asynchronous and non-blocking.

Let’s illustrate how the Promise works asynchronously and non-blocking, highlighting the key differences compared to
other PHP implementations.

In the examples below, we will create 10 Promises, each resolving in a specified time. To simulate intensive processing,
we use the sleep function.

## Example Setup

For this test, the first Promise will take 10 seconds to resolve, the second 9 seconds, the third 8 seconds, and so on,
with the last Promise resolving in just 1 second. This setup ensures that the total resolution time varies based on
implementation behavior.

### Expected Behavior

1. Blocking Implementation:

   If the implementation is blocking, the total execution time will be approximately the sum of all the Promises (10 +
   9 + 8 + ... + 1 = 55 seconds). In this case, the Promises will resolve in the order they were created.

2. Non-Blocking Implementation:

   If the implementation is non-blocking, the total execution time will be equivalent to the duration of the longest
   Promise (10 seconds). Promises will resolve in the order of completion rather than the order of creation.

The `ByJG PHPThread Promise` demonstrates non-blocking behavior, completing all tasks in just 10 seconds while resolving
Promises as they finish.

### Comparison Table

| Implementation         | Time to run    | Resolve Order              |
|------------------------|----------------|----------------------------|
| ByJG PHPThread Promise | 10.044 seconds | In the order of completion |
| ReactPHP Promise       | 55.038 seconds | In the order of creation   |
| Guzzle Promise         | 55.035 seconds | In the order of creation   |

## Test Code

### ByJG Thread Promise

Install the package:

```bash
composer require byjg/phpthread
```

Then created a file called `benchmark.php` with the following content:

```php
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
```

When you run the script you will see the following output:

```bash
$ time php benchmark.php 

Promise 1 p_81f8ea5c33f2d726ad936a83f087b3c5 is created!
Promise 2 p_183cfa6fe31ff70a3990f44e6817390e is created!
Promise 3 p_5b85c193270479c06b05e452bd7cfbf8 is created!
Promise 4 p_9c3269e8546c4bc0a462d7ef1c7df0b4 is created!
Promise 5 p_cbb65e0f071849d6179afa542e910d67 is created!
Promise 6 p_bfb297064910884e150380a8797aa84b is created!
Promise 7 p_a20b55b72d28ec40d12615c1c49a9863 is created!
Promise 8 p_279fa10c9b6b93ad9ee68de8ae1c1cf8 is created!
Promise 9 p_36f77eb3ac342f1da25c8b18c20c9043 is created!
Promise 10 p_d62f8a05c1427a536de4dbd8c46ba735 is created!
Success: Promise 10 is fulfilled!
Success: Promise 9 is fulfilled!
Success: Promise 8 is fulfilled!
Success: Promise 7 is fulfilled!
Success: Promise 6 is fulfilled!
Success: Promise 5 is fulfilled!
Success: Promise 4 is fulfilled!
Success: Promise 3 is fulfilled!
Success: Promise 2 is fulfilled!
Success: Promise 1 is fulfilled!

real    0m10.044s
user    0m6.857s
sys     0m3.327s
```

### ReactPHP Promise

Install the package:

```bash
composer require react/promise
composer require react/event-loop
```

Then created a file called `benchmark-reactphp.php` with the following content:

```php
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
```

When you run the script you will see the following output:

```bash
$ time php benchmark.php

Success: Promise 1 is fulfilled!
Success: Promise 2 is fulfilled!
Success: Promise 3 is fulfilled!
Success: Promise 4 is fulfilled!
Success: Promise 5 is fulfilled!
Success: Promise 6 is fulfilled!
Success: Promise 7 is fulfilled!
Success: Promise 8 is fulfilled!
Success: Promise 9 is fulfilled!
Success: Promise 10 is fulfilled!

real    0m55.038s
user    0m0.020s
sys     0m0.017s
```

### Guzzle Promise

Install the package:

```bash
composer require guzzlehttp/promises
```

Then created a file called `benchmark-guzzle.php` with the following content:

```php
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
```

When you run the script you will see the following output:

```bash
$ time php benchmark-guzzle.php 

Promise 1 is created!
Promise 2 is created!
Promise 3 is created!
Promise 4 is created!
Promise 5 is created!
Promise 6 is created!
Promise 7 is created!
Promise 8 is created!
Promise 9 is created!
Promise 10 is created!
Success: Promise 1 is fulfilled!
Success: Promise 2 is fulfilled!
Success: Promise 3 is fulfilled!
Success: Promise 4 is fulfilled!
Success: Promise 5 is fulfilled!
Success: Promise 6 is fulfilled!
Success: Promise 7 is fulfilled!
Success: Promise 8 is fulfilled!
Success: Promise 9 is fulfilled!
Success: Promise 10 is fulfilled!

real    0m55.035s
user    0m0.019s
sys     0m0.015s
```
