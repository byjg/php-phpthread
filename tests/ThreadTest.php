<?php


use PHPUnit\Framework\TestCase;

use ByJG\PHPThread\Thread;

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

        $this->assertEquals(Thread::STATUS_NOT_STARTED, $thr1->getStatus());
        $this->assertEquals(Thread::STATUS_NOT_STARTED, $thr2->getStatus());

        // Start Threads
        $thr1->execute(2);
        $thr2->execute(1);

        $this->assertEquals(Thread::STATUS_RUNNING, $thr1->getStatus());
        $this->assertEquals(Thread::STATUS_RUNNING, $thr2->getStatus());

        // Make sure they are running
        $this->assertTrue($thr1->isAlive());
        $this->assertTrue($thr2->isAlive());

        // Wait to Finish
        $thr1->waitFinish();
        $thr2->waitFinish();

        $this->assertEquals(Thread::STATUS_FINISHED, $thr1->getStatus());
        $this->assertEquals(Thread::STATUS_FINISHED, $thr2->getStatus());

        // Make sure they're finished
        $this->assertFalse($thr1->isAlive());
        $this->assertFalse($thr2->isAlive());

        // Get the thread result
        $this->assertEquals(6, $thr1->getResult());
        $this->assertEquals(3, $thr2->getResult());
    }
}
