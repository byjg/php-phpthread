## Thread

Assume for the examples below the class 'Foo' and the method 'bar':

```php
require_once('vendor/autoload.php');

// Method to be executed in a thread
$threadClousure = function ($t)
    {
        echo "Starting thread #$t" . PHP_EOL;;
        sleep(1 * rand(1, 5));
        for ($i = 0; $i < 10; $i++)
        {
            echo "Hello from thread #$t, i=$i" . PHP_EOL;
            sleep(1);
        }
        echo "Ending thread #$t" . PHP_EOL;
    
        return $t;
    };
```

## Basic Thread Usage

```php
// Create the Threads passing a callable
$thread1 = ByJG\PHPThread\Thread::create( $threadClousure );
$thread2 = ByJG\PHPThread\Thread::create( $threadClousure );

// Start the threads and passing parameters
$thread1->execute(1);
$thread2->execute(2);

// Wait the threads to finish
$thread1->waitFinish();
$thread2->waitFinish();

// Get the thread result
echo "Thread Result 1: " . $thread1->getResult();
echo "Thread Result 2: " . $thread2->getResult();
```
