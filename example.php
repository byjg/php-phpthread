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
}

try
{
    $t = array();

	// Create the threads
    for ($i = 0; $i < 10; $i++)
    {
		// Create a new instance of the Thread class, pointing to "Foo" function
        $thr = new \ByJG\PHPThread\Thread('Foo');

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
