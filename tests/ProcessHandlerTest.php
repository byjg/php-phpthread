<?php

namespace Tests;

use ByJG\PHPThread\Handler\ProcessHandler;
use ByJG\PHPThread\ThreadStatus;
use PHPUnit\Framework\TestCase;

class ProcessHandlerTest extends TestCase
{
    #[\Override]
    public function tearDown(): void
    {
        ProcessHandler::setPhpBinary(null);
    }

    public function testResult()
    {
        $thread = new ProcessHandler();
        $thread->setClosure(function ($a, $b) {
            usleep(100_000);
            return $a * $b;
        });

        $this->assertEquals(ThreadStatus::NotStarted, $thread->getStatus());
        $this->assertNull($thread->getResult());

        $thread->start(2, 3);
        $this->assertEquals(ThreadStatus::Running, $thread->getStatus());
        $this->assertTrue($thread->isRunning());

        $thread->join();
        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
        $this->assertFalse($thread->isRunning());
        $this->assertEquals(6, $thread->getResult());
        $this->assertEquals(6, $thread->getResult());

        // Running it again replaces the result
        $thread->start(4, 5);
        $this->assertEquals(20, $thread->getResult());
        $this->assertEquals(ProcessHandler::class, $thread->getClassName());
    }

    public function testGetResultWaitsForTheProcess()
    {
        $thread = new ProcessHandler();
        $thread->setClosure(function () {
            usleep(100_000);
            return 'done';
        });
        $thread->start();

        $this->assertEquals('done', $thread->getResult());
        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
    }

    public function testCapturedVariablesAndArgs()
    {
        $prefix = 'Hello';
        $object = new \ArrayObject(['a' => 1]);

        $thread = new ProcessHandler();
        $thread->setClosure(fn(string $name, array $list) => [
            "$prefix $name",
            $object['a'] + count($list),
            getmypid(),
        ]);
        $thread->start('World', [1, 2, 3]);

        [$greeting, $sum, $pid] = $thread->getResult();
        $this->assertEquals('Hello World', $greeting);
        $this->assertEquals(4, $sum);
        // It really ran in another process
        $this->assertNotEquals(getmypid(), $pid);
    }

    public function testExceptionIsRethrownOnEveryGetResult()
    {
        $thread = new ProcessHandler();
        $thread->setClosure(fn() => throw new \LogicException('Thread failed', 10));
        $thread->start();
        $thread->join();

        foreach ([1, 2] as $attempt) {
            try {
                $thread->getResult();
                $this->fail("getResult() call $attempt did not re-throw the exception");
            } catch (\LogicException $ex) {
                $this->assertEquals('Thread failed', $ex->getMessage());
                $this->assertEquals(10, $ex->getCode());
            }
        }
        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
    }

    public function testResultThatCannotBeSerialized()
    {
        $thread = new ProcessHandler();
        $thread->setClosure(fn() => fn() => 1);
        $thread->start();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Serialization of 'Closure' is not allowed");
        $thread->getResult();
    }

    public function testProcessEndedWithoutResult()
    {
        $thread = new ProcessHandler();
        $thread->setClosure(fn() => exit(3));
        $thread->start();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The PHP process ended without a result (exit code 3)');
        $thread->getResult();
    }

    public function testLargeResult()
    {
        // Much bigger than a pipe buffer (64 KiB): the child would block forever if the parent did not read it
        $thread = new ProcessHandler();
        $thread->setClosure(fn($size) => str_repeat('x', $size));
        $thread->start(5 * 1024 * 1024);

        $this->assertEquals(5 * 1024 * 1024, strlen($thread->getResult()));
    }

    public function testOnFinish()
    {
        $file = tempnam(sys_get_temp_dir(), 'onfinish');

        // onFinish runs in the child after the closure, even when it throws, as in ForkHandler
        $thread = new ProcessHandler(function () use ($file) {
            file_put_contents($file, 'finished:' . getmypid());
        });
        $thread->setClosure(fn() => throw new \LogicException('Thread failed'));
        $thread->start();
        $thread->join();

        $content = file_get_contents($file);
        unlink($file);
        $this->assertStringStartsWith('finished:', $content);
        $this->assertNotEquals('finished:' . getmypid(), $content);
    }

    public function testTerminate()
    {
        $thread = new ProcessHandler();
        $thread->setClosure(function () {
            sleep(10);
            return 1;
        });

        $start = microtime(true);
        $thread->start();
        $this->assertTrue($thread->isRunning());

        $thread->terminate(SIGKILL, true);
        $this->assertLessThan(5, microtime(true) - $start);
        $this->assertFalse($thread->isRunning());
        $this->assertEquals(ThreadStatus::Finished, $thread->getStatus());
        // A terminated process has no result
        $this->assertNull($thread->getResult());
    }

    public function testTerminateWithoutWait()
    {
        $thread = new ProcessHandler();
        $thread->setClosure(function () {
            sleep(10);
            return 1;
        });
        $thread->start();
        $thread->terminate(SIGTERM);

        $start = microtime(true);
        $thread->join();
        $this->assertLessThan(5, microtime(true) - $start);
        $this->assertNull($thread->getResult());
    }

    public function testRunsInParallel()
    {
        $closure = function ($arg) {
            usleep(250_000);
            return $arg;
        };

        $start = microtime(true);
        $thr1 = new ProcessHandler();
        $thr1->setClosure($closure);
        $thr2 = new ProcessHandler();
        $thr2->setClosure($closure);
        $thr1->start(1);
        $thr2->start(2);

        $this->assertEquals(1, $thr1->getResult());
        $this->assertEquals(2, $thr2->getResult());

        // Sequentially it would take at least the sum of the sleeps (0.5 s), whatever the process start-up costs
        $this->assertLessThan(0.5, microtime(true) - $start);
    }

    public function testPhpBinary()
    {
        // The tests run in the CLI
        $this->assertEquals(PHP_BINARY, ProcessHandler::getPhpBinary());
        $this->assertTrue(ProcessHandler::isAvailable());

        // A custom binary is used to start the process: a script that sets a variable and runs PHP
        $binary = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($binary, "#!/bin/sh\nPHPTHREAD_BINARY=custom exec " . PHP_BINARY . ' "$@"' . "\n");
        chmod($binary, 0755);
        ProcessHandler::setPhpBinary($binary);
        $this->assertEquals($binary, ProcessHandler::getPhpBinary());

        $thread = new ProcessHandler();
        $thread->setClosure(fn() => getenv('PHPTHREAD_BINARY'));
        $thread->start();
        $result = $thread->getResult();
        unlink($binary);
        $this->assertEquals('custom', $result);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("'/not/a/php' is not an executable file");
        ProcessHandler::setPhpBinary('/not/a/php');
    }
}
