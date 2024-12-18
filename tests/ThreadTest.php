<?php


use ByJG\PHPThread\Thread;
use ByJG\PHPThread\ThreadStatus;
use PHPUnit\Framework\TestCase;

class ThreadTest extends TestCase
{
    public function testThread()
    {
        $closure = function ($arg) {
            sleep($arg*3);
            return $arg * 3;
        };

        $thr1 = Thread::create($closure);
        $thr2 = Thread::create($closure);

        $this->assertEquals(ThreadStatus::notStarted, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::notStarted, $thr2->getStatus());

        // Start Threads
        $thr1->execute(2);
        $thr2->execute(1);

        $this->assertEquals(ThreadStatus::running, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::running, $thr2->getStatus());

        // Make sure they are running
        $this->assertTrue($thr1->isAlive());
        $this->assertTrue($thr2->isAlive());

        // Wait to Finish
        $thr1->waitFinish();
        $thr2->waitFinish();

        $this->assertEquals(ThreadStatus::finished, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::finished, $thr2->getStatus());

        // Make sure they're finished
        $this->assertFalse($thr1->isAlive());
        $this->assertFalse($thr2->isAlive());

        // Get the thread result
        $this->assertEquals(6, $thr1->getResult());
        $this->assertEquals(3, $thr2->getResult());
    }
}
