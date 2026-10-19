<?php

namespace Tests;

use ByJG\PHPThread\Thread;
use ByJG\PHPThread\ThreadStatus;
use PHPUnit\Framework\TestCase;

class ThreadTest extends TestCase
{
    #[\Override]
    public function tearDown(): void
    {
        Thread::gc();
    }

    public function testThread()
    {
        $closure = function ($arg) {
            sleep(1 * $arg);
            return $arg * 3;
        };

        $thr1 = Thread::create($closure);
        $thr2 = Thread::create($closure);

        $this->assertEquals(ThreadStatus::NotStarted, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::NotStarted, $thr2->getStatus());

        // Start Threads
        $thr1->start(2);
        $thr2->start(1);

        $this->assertEquals(ThreadStatus::Running, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::Running, $thr2->getStatus());

        // Make sure they are running
        $this->assertTrue($thr1->isRunning());
        $this->assertTrue($thr2->isRunning());

        // Wait to Finish
        $thr1->join();
        $thr2->join();

        $this->assertEquals(ThreadStatus::Finished, $thr1->getStatus());
        $this->assertEquals(ThreadStatus::Finished, $thr2->getStatus());

        // Make sure they're finished
        $this->assertFalse($thr1->isRunning());
        $this->assertFalse($thr2->isRunning());

        // Get the thread result
        $this->assertEquals(6, $thr1->getResult());
        $this->assertEquals(3, $thr2->getResult());
    }

    public function testStatusAndResultAfterGetResult()
    {
        $thread = Thread::create(fn($arg) => $arg * 3);
        $thread->start(2);
        $thread->join();

        $this->assertEquals(6, $thread->getResult());

        // Reading the result does not reset the thread, and the result can be read again
        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
        $this->assertEquals(6, $thread->getResult());
    }

    public function testExceptionIsRethrownOnEveryGetResult()
    {
        $thread = Thread::create(fn() => throw new \LogicException('Thread failed'));
        $thread->start();
        $thread->join();

        foreach ([1, 2] as $attempt) {
            try {
                $thread->getResult();
                $this->fail("getResult() call $attempt did not re-throw the exception");
            } catch (\LogicException $ex) {
                $this->assertEquals('Thread failed', $ex->getMessage());
            }
        }
        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
    }
}
