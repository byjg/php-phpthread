<?php

namespace Tests;

use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\ThreadStatus;
use PHPUnit\Framework\TestCase;

class SyncHandlerTest extends TestCase
{
    public function testResult()
    {
        $thread = new SyncHandler();
        $thread->setClosure(fn($a, $b) => $a * $b);

        $this->assertEquals(ThreadStatus::NotStarted, $thread->getStatus());
        $this->assertNull($thread->getResult());

        $thread->start(2, 3);

        // The closure already ran when start() returned
        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
        $this->assertFalse($thread->isRunning());
        $thread->join();
        $this->assertEquals(6, $thread->getResult());
        $this->assertEquals(6, $thread->getResult());

        // Running it again replaces the result
        $thread->start(4, 5);
        $this->assertEquals(20, $thread->getResult());
    }

    public function testExceptionIsRethrownOnEveryGetResult()
    {
        $thread = new SyncHandler();
        $thread->setClosure(fn() => throw new \LogicException('Thread failed'));
        $thread->start();

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

    public function testOnFinish()
    {
        $calls = [];
        $thread = new SyncHandler(function () use (&$calls) {
            $calls[] = func_get_args();
        });
        $thread->setClosure(function () use (&$calls) {
            $calls[] = 'closure';
            throw new \LogicException('Thread failed');
        });

        $thread->start();

        // Called with no argument after the closure, even when it throws (as ForkHandler does in the child)
        $this->assertEquals(['closure', []], $calls);
    }

    public function testTerminateDoesNothing()
    {
        $thread = new SyncHandler();
        $thread->setClosure(fn() => 10);
        $thread->start();
        $thread->terminate();

        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
        $this->assertEquals(10, $thread->getResult());
        $this->assertEquals(SyncHandler::class, $thread->getClassName());
    }
}
