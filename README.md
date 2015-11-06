# phpthread
[![Code Climate](https://codeclimate.com/github/byjg/phpthread/badges/gpa.svg)](https://codeclimate.com/github/byjg/phpthread)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/842a5377-bbda-44f2-9163-b40dc650dc1f/mini.png)](https://insight.sensiolabs.com/projects/842a5377-bbda-44f2-9163-b40dc650dc1f)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/byjg/phpthread/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/byjg/phpthread/?branch=master)

Native Implementation of Threads in PHP.

A class to spawn a thread. Only works in *nix environments, as Windows platform is missing libpcntl. Forks the process.

This Class was originally was developed by "Superuser"

Original Version ID: Thread.class.php 23 2012-01-23 09:40:32Z superuser

This file was changed by JG based on the post at:
 * http://villavu.com/forum/showthread.php?t=73623

Install in ubuntu:
 * http://ubuntuforums.org/showthread.php?t=549953

# Basic Usage

```php
require_once('vendor/autoload.php');

// Method to be executed in a thread
function Foo($t)
{
	echo "Starint thread #$t" . PHP_EOL;;
    sleep(1 * rand(1, 5));
	for ($i = 0; $i < 10; $i++)
	{
		echo "Hello from thread #$t, i=$i" . PHP_EOL;
		sleep(1);
	}
    echo "Ending thread #$t" . PHP_EOL;
}

try
{
    $t = array();

	// Create the threads
    for ($i = 0; $i < 10; $i++)
    {
		// Create a new instance of the Thread class, pointing to "Foo" function
        $thr = new ByJG\PHPThread\Thread('Foo');

		// Started the method "Foo" in a tread
        $thr->start($i);

		// Save the thread reference to be manipulate
        $t[] = $thr;
    }

    $done = false;

	// It is important to check if all threads are done
	// otherwise will be terminate when the php script is finished;
    while (!$done)
    {
        sleep(1);

        $done = true;

        foreach ($t as $thread)
        {
            if ($thread->isAlive())
            {
                $done = false;
                break;
            }
        }
    }
}
catch (Exception $e)
{
    echo 'Exception: ' . $e . PHP_EOL;
}
```

# Thread Pool Usage

You can create a pool of threads.

```php
// Create a instance of the ThreadPool
$threadPool = new \ByJG\PHPThread\ThreadPool();

// Create the threads
for ($i = 0; $i < 10; $i++)
{
	// Queue a worker pointing to "Foo" function and pass the required parameters
	$threadPool->queueWorker('Foo', [ $i ]);
}

// Starts all the threads in the queue
$threadPool->startWorkers();

// Wait until there is no more active workers
while($threadPool->activeWorkers() > 0)
{
	echo "Active Workers : " . $threadPool->activeWorkers() . "\n";
	sleep(1);
}

// Get the return value from the thread.
foreach ($threadPool->getThreads() as $thid)
{
	echo 'Result: ' . $threadPool->getThreadResult($thid) . "\n";
}

echo "\n\nEnded!\n";
```

*Important Note*

In order to get working the getResult of the thread is necessary setup a file in '__DIR__/config/cacheconfig.php' with 
the follow contents for setup the maxthread.

```php
<?php

return [
    'phpthread' => [
        'instance' => '\\ByJG\\Cache\\ShmopCacheEngine',
        'shmop' => [
            'max-size' => 0x100000,
            'default-permission' => '0700'
        ]
    ]
];
```

## Install

Just type: `composer require "byjg/phpthread=~1.1"`

## FAQ

**How do I instantiate a method class?**

```php
$thr = new ByJG\PHPThread\Thread(array('classname', 'methodname'));
```

or

```php
$instance = new myClass();
$thr = new ByJG\PHPThread\Thread(array($instance, 'methodname'));
```

