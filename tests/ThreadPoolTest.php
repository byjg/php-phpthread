<?php


use PHPUnit\Framework\TestCase;

class ThreadPoolTest extends TestCase
{
    protected function getClosure()
    {
        return function ($arg) {
            usleep(100 * $arg);
            return $arg * 3;
        };
    }

    public function testThread()
    {
        $pool = new \ByJG\PHPThread\ThreadPool();

        $th1 = $pool->queueWorker($this->getClosure(), 3);
        $th2 = $pool->queueWorker($this->getClosure(), 2);
        $this->assertEquals(0, $pool->activeWorkers());

        $pool->startPool();
        $this->assertEquals(2, $pool->activeWorkers());

        $th3 = $pool->queueWorker($this->getClosure(), 1);
        $this->assertEquals(3, $pool->activeWorkers());

        $pool->waitWorkers();

        $this->assertEquals(0, $pool->activeWorkers());

        $this->assertEquals(9, $pool->getThreadResult($th1));
        $this->assertEquals(6, $pool->getThreadResult($th2));
        $this->assertEquals(3, $pool->getThreadResult($th3));
    }


    public function testThreadResult()
    {
        $pool = new \ByJG\PHPThread\ThreadPool();

        $th1 = $pool->queueWorker($this->getClosure(), 3);
        $th2 = $pool->queueWorker($this->getClosure(), 2);
        $this->assertEquals(0, $pool->activeWorkers());

        $this->assertNull($pool->getThreadResult($th1));
        $this->assertNull($pool->getThreadResult($th2));
    }

    public function testThreadStart()
    {
        $pool = new \ByJG\PHPThread\ThreadPool();

        $th1 = $pool->queueWorker($this->getClosure(), 3);
        $th2 = $pool->queueWorker($this->getClosure(), 2);
        $this->assertEquals(0, $pool->activeWorkers());

        $pool->startPool();
        $this->assertEquals(2, $pool->activeWorkers());

        $pool->stopPool();
        $this->assertEquals(0, $pool->activeWorkers());
    }
}
