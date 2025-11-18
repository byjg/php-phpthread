<?php

require __DIR__ . '/../vendor/autoload.php';

use ByJG\PHPThread\Thread;

$fn = function ($a) {
    echo "Comecou $a\n";
    sleep(1);
    echo "Terminou\n";
    return $a;
};

$x = Thread::create($fn);
$x->start(10);
$x->join();
sleep(1);
echo $x->getResult();


// Method to be executed in a thread
$threadClousure = function ($t) {
    echo "Starting thread #$t" . PHP_EOL;;
    sleep(1 * rand(1, 5));
    for ($i = 0; $i < 10; $i++) {
        echo "Hello from thread #$t, i=$i" . PHP_EOL;
        sleep(1);
    }
    echo "Ending thread #$t" . PHP_EOL;

    return $t;
};

// Create the Threads passing a callable
$thread1 = ByJG\PHPThread\Thread::create($threadClousure);
$thread2 = ByJG\PHPThread\Thread::create($threadClousure);

// Start the threads and passing parameters
$thread1->start(1);
$thread2->start(2);

// Wait the threads to finish
$thread1->join();
$thread2->join();

// Get the thread result
echo "Thread Result 1: " . $thread1->getResult();
echo "Thread Result 2: " . $thread2->getResult();
