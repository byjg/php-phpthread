<?php


use ByJG\PHPThread\Thread;
use ByJG\PHPThread\ThreadStatus;
use PHPUnit\Framework\TestCase;

class ThreadTest extends TestCase
{
    public function tearDown(): void
    {
        Thread::gc();
    }

    public function testThread()
    {
        $closure = function ($arg) {
            usleep(100 * $arg);
            return $arg * 3;
        };

        $thr1 = Thread::create($closure);
        $thr2 = Thread::create($closure);

        $this->assertEquals(ThreadStatus::notStarted, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::notStarted, $thr2->getStatus());

        // Start Threads
        $thr1->start(2);
        $thr2->start(1);

        $this->assertEquals(ThreadStatus::running, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::running, $thr2->getStatus());

        // Make sure they are running
        $this->assertTrue($thr1->isRunning());
        $this->assertTrue($thr2->isRunning());

        // Wait to Finish
        $thr1->join();
        $thr2->join();

        $this->assertEquals(ThreadStatus::finished, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::finished, $thr2->getStatus());

        // Make sure they're finished
        $this->assertFalse($thr1->isRunning());
        $this->assertFalse($thr2->isRunning());

        // Get the thread result
        $this->assertEquals(6, $thr1->getResult());
        $this->assertEquals(3, $thr2->getResult());
    }
}
