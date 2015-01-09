# phpthread

Native Implementation of Threads in PHP.

A class to spawn a thread. Only works in *nix environments, as Windows platform is missing libpcntl. Forks the process.

This Class was originally was developed by "Superuser"

Original Version ID: Thread.class.php 23 2012-01-23 09:40:32Z superuser

This file was changed by JG based on the post at:
 * http://villavu.com/forum/showthread.php?t=73623

Install in ubuntu:
 * http://ubuntuforums.org/showthread.php?t=549953

# Usage

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

