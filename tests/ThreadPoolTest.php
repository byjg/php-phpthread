<?php

use ByJG\PHPThread\Thread;
use ByJG\PHPThread\ThreadPool;
use PHPUnit\Framework\TestCase;

class ThreadPoolTest extends TestCase
{
    public function tearDown(): void
    {
        Thread::gc();
    }

    protected function getClosure()
    {
        return function ($arg) {
            sleep(1 * $arg);
            return $arg * 3;
        };
    }

    public function testThread()
    {
        $pool = new ThreadPool();

        $th1 = $pool->addWorker($this->getClosure(), 3);
        $th2 = $pool->addWorker($this->getClosure(), 2);
        $this->assertEquals(0, $pool->countActiveWorkers());

        $pool->startAll();
        $this->assertEquals(2, $pool->countActiveWorkers());

        $th3 = $pool->addWorker($this->getClosure(), 1);
        $this->assertEquals(3, $pool->countActiveWorkers());

        $pool->waitForCompletion();

        $this->assertEquals(0, $pool->countActiveWorkers());

        $this->assertEquals(9, $pool->getThreadResult($th1));
        $this->assertEquals(6, $pool->getThreadResult($th2));
        $this->assertEquals(3, $pool->getThreadResult($th3));
    }


    public function testThreadResult()
    {
        $pool = new ThreadPool();

        $th1 = $pool->addWorker($this->getClosure(), 3);
        $th2 = $pool->addWorker($this->getClosure(), 2);
        $this->assertEquals(0, $pool->countActiveWorkers());

        $this->assertNull($pool->getThreadResult($th1));
        $this->assertNull($pool->getThreadResult($th2));
    }

    public function testThreadStart()
    {
        $pool = new ThreadPool();

        $th1 = $pool->addWorker($this->getClosure(), 3);
        $th2 = $pool->addWorker($this->getClosure(), 2);
        $this->assertEquals(0, $pool->countActiveWorkers());

        $pool->startAll();
        $this->assertEquals(2, $pool->countActiveWorkers());

        $pool->stopAll();
        $this->assertEquals(0, $pool->countActiveWorkers());
    }
}
