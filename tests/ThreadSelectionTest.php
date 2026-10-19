<?php

namespace Tests;

use ByJG\PHPThread\Handler\ForkHandler;
use ByJG\PHPThread\Handler\ParallelHandler;
use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\Thread;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThreadSelectionTest extends TestCase
{
    #[\Override]
    public function tearDown(): void
    {
        Thread::useHandler(null);
    }

    public static function environmentProvider(): array
    {
        return [
            'cli, parallel and pcntl' => [true, true, true, ParallelHandler::class],
            'cli, pcntl only' => [true, false, true, ForkHandler::class],
            'cli, no parallel nor pcntl' => [true, false, false, SyncHandler::class],
            'fpm, parallel and pcntl' => [false, true, true, SyncHandler::class],
            'fpm, pcntl only' => [false, false, true, SyncHandler::class],
            'fpm, no parallel nor pcntl' => [false, false, false, SyncHandler::class],
        ];
    }

    #[DataProvider('environmentProvider')]
    public function testHandlerClass(bool $cli, bool $parallel, bool $fork, string $expected)
    {
        ThreadInEnvironment::$cli = $cli;
        ThreadInEnvironment::$parallel = $parallel;
        ThreadInEnvironment::$fork = $fork;

        $this->assertEquals($expected, ThreadInEnvironment::handlerClass());
    }

    public function testCreateFallsBackToSyncHandler()
    {
        ThreadInEnvironment::$cli = false;
        ThreadInEnvironment::$parallel = false;
        ThreadInEnvironment::$fork = true;

        $finished = false;
        $thread = ThreadInEnvironment::create(fn($arg) => $arg * 3, function () use (&$finished) {
            $finished = true;
        });
        $this->assertInstanceOf(SyncHandler::class, $thread);

        $thread->start(2);
        $this->assertTrue($finished);
        $this->assertEquals(6, $thread->getResult());
    }

    public function testUseHandler()
    {
        // The tests run in the CLI with pcntl (or parallel), so the automatic choice is not SyncHandler
        $automatic = Thread::handlerClass();
        $this->assertNotEquals(SyncHandler::class, $automatic);

        Thread::useHandler(SyncHandler::class);
        $this->assertEquals(SyncHandler::class, Thread::handlerClass());
        $this->assertInstanceOf(SyncHandler::class, Thread::create(fn() => null));

        // It is also forced when the automatic choice would be another handler
        ThreadInEnvironment::$cli = true;
        ThreadInEnvironment::$parallel = true;
        ThreadInEnvironment::$fork = true;
        $this->assertEquals(SyncHandler::class, ThreadInEnvironment::handlerClass());

        Thread::useHandler(null);
        $this->assertEquals($automatic, Thread::handlerClass());
        $this->assertInstanceOf($automatic, Thread::create(fn() => null));
    }

    public function testUseHandlerInvalidClass()
    {
        $this->expectException(\InvalidArgumentException::class);
        Thread::useHandler(\stdClass::class);
    }
}
