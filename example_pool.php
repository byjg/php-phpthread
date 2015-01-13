<?php

require_once('vendor/autoload.php');

// Method to be executed in a thread
function Foo($t)
{
	echo "Starting thread #$t" . PHP_EOL;;
    sleep(1 * rand(1, 5));
	for ($i = 0; $i < 10; $i++)
	{
		echo "Hello from thread #$t, i=$i" . PHP_EOL;
		sleep(1);
	}
    echo "Ending thread #$t" . PHP_EOL;

	return uniqid("Thread_{$t}_");
}


try
{
	$threadPool = new ByJG\PHPThread\ThreadPool();

	// Create the threads
    for ($i = 0; $i < 10; $i++)
    {
		// Create a new instance of the Thread class, pointing to "Foo" function
        $threadPool->queueWorker('Foo', [ $i ]);
    }

	$threadPool->startWorkers();

	while($threadPool->activeWorkers() > 0)
	{
		echo "Active Workers : " . $threadPool->activeWorkers() . "\n";
		sleep(1);
	}

	foreach ($threadPool->getThreads() as $thid)
	{
		echo 'Result Thread ' . $threadPool->getThreadResult($thid) . "\n";
	}

	echo "\n\nEnded!\n";

}
catch (Exception $e)
{
    echo 'Exception: ' . $e . PHP_EOL;
}

