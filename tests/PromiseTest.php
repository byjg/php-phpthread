<?php

namespace Tests;

use ByJG\PHPThread\Handler\ForkHandler;
use ByJG\PHPThread\Handler\SyncHandler;
use ByJG\PHPThread\Promise;
use ByJG\PHPThread\PromiseStatus;
use ByJG\PHPThread\Thread;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every scenario runs with the ForkHandler and with the SyncHandler fallback
 */
class PromiseTest extends TestCase
{
    public static function handlerProvider(): array
    {
        return [
            'fork' => [ForkHandler::class],
            'sync' => [SyncHandler::class],
        ];
    }

    #[\Override]
    public function setUp(): void
    {
        if (extension_loaded('parallel')) {
            $this->markTestSkipped('Promise is not compatible with parallel extension');
        }
    }

    #[\Override]
    public function tearDown(): void
    {
        Thread::useHandler(null);
        Promise::gc();
    }

    #[DataProvider('handlerProvider')]
    public function testPromiseFulfilled(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $x = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        // Test if the promise is pending
        $this->assertEquals(1, $x->await());

        // Test if the promise is fulfilled
        $this->assertEquals(PromiseStatus::Fulfilled, $x->getStatus());

        // Test then
        $this->assertEquals(3, $x->then(fn($resolve) => $resolve + 2)->await());
        $this->assertEquals(11, $x->then(fn($resolve) => $resolve + 10)->await());
    }

    #[DataProvider('handlerProvider')]
    public function testPromiseFulfilledSequence(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $result = Promise::create(function ($resolve, $reject) {
            $resolve(1);
        })
            ->then(fn($resolve) => $resolve + 2)
            ->then(fn($resolve) => $resolve + 10)
            ->await();

        $this->assertEquals(13, $result);
    }

    #[DataProvider('handlerProvider')]
    public function testPromiseRejected(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $x = new Promise(function ($resolve, $reject) {
            $reject(1);
        });

        // Test if the promise is pending
        $this->assertEquals(1, $x->await());

        // Test if the promise is rejected
        $this->assertEquals(PromiseStatus::Rejected, $x->getStatus());

        // Test then
        $this->assertEquals(6, $x->then(fn($resolve) => $resolve + 2, fn($reject) => $reject + 5)->await());
        $this->assertEquals(3, $x->then(fn($resolve) => $resolve + 10, fn($reject) => $reject + 2)->await());
    }

    #[DataProvider('handlerProvider')]
    public function testPromiseRejectedSequence(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $result = Promise::create(function ($resolve, $reject) {
            $reject(1);
        })
            ->then(fn($resolve) => $resolve + 2, fn($reject) => $reject + 5)
            ->then(fn($resolve) => $resolve + 10, fn($reject) => $reject + 2)
            ->await();

        // The first onRejected handles the rejection, so the chain continues fulfilled: 1 + 5 + 10
        $this->assertEquals(16, $result);
    }

    #[DataProvider('handlerProvider')]
    public function testAllFulfilled(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise1 = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $promise2 = new Promise(function ($resolve, $reject) {
            $resolve(2);
        });

        $promise3 = new Promise(function ($resolve, $reject) {
            $resolve(3);
        });

        $result = Promise::all($promise1, $promise2, $promise3)->await();

        $this->assertEquals([1, 2, 3], $result);
    }

    #[DataProvider('handlerProvider')]
    public function testAllReject(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise1 = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $promise2 = new Promise(function ($resolve, $reject) {
            $reject(2);
        });

        $promise3 = new Promise(function ($resolve, $reject) {
            $resolve(3);
        });

        $result = Promise::all($promise1, $promise2, $promise3)
            ->then(
                fn($resolve) => "Success: $resolve",
                fn($reason) => "Reason: $reason"
            )->await();

        $this->assertEquals("Reason: 2", $result);
    }

    #[DataProvider('handlerProvider')]
    public function testAllFulfilledCatch(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise1 = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $promise2 = new Promise(function ($resolve, $reject) {
            $resolve(2);
        });

        $promise3 = new Promise(function ($resolve, $reject) {
            $resolve(3);
        });

        $result = Promise::all($promise1, $promise2, $promise3)
            ->catch(
                fn($reason) => "Reason: $reason"
            )->await();

        $this->assertEquals([1, 2, 3], $result);
    }

    #[DataProvider('handlerProvider')]
    public function testAllRejectCatch(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise1 = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $promise2 = new Promise(function ($resolve, $reject) {
            $reject(2);
        });

        $promise3 = new Promise(function ($resolve, $reject) {
            $resolve(3);
        });

        $result = Promise::all($promise1, $promise2, $promise3)
            ->catch(
                fn($reason) => "Reason: $reason"
            )->await();

        $this->assertEquals("Reason: 2", $result);
    }

    public static function raceProvider(): array
    {
        return [
            // promise2 settles first, while promise1 and promise3 sleep in their own processes
            'fork' => [ForkHandler::class, 2],
            // Each executor runs to the end when its promise is created, so all three are already
            // settled when race() runs and the first one in the list wins, as in JavaScript
            'sync' => [SyncHandler::class, 1],
        ];
    }

    #[DataProvider('raceProvider')]
    public function testRaceFulfilled(string $handlerClass, int $expected)
    {
        Thread::useHandler($handlerClass);

        $promise1 = new Promise(function ($resolve, $reject) {
            sleep(1);
            $resolve(1);
        });

        $promise2 = new Promise(function ($resolve, $reject) {
            $resolve(2);
        });

        $promise3 = new Promise(function ($resolve, $reject) {
            sleep(1);
            $resolve(3);
        });

        $result = Promise::race($promise1, $promise2, $promise3)->await();

        $this->assertEquals($expected, $result);

    }

    #[DataProvider('handlerProvider')]
    public function testPromiseException(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('Error Message');
        });

        $result = $promise->await();

        $this->assertEquals('Error Message', $result->getMessage());
    }

    #[DataProvider('handlerProvider')]
    public function testPromiseException2(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('Error Message 2');
        });

        $result = $promise->then(
            fn($resolve) => $resolve,
            fn($reject) => $reject->getMessage()
        )->await();

        $this->assertEquals('Error Message 2', $result);
    }

    #[DataProvider('handlerProvider')]
    public function testPromiseException3(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $result = $promise->then(
            fn($resolve) => throw new \Exception('Error Message 3'),
        )->await();

        $this->assertEquals('Error Message 3', $result->getMessage());
    }

    #[DataProvider('handlerProvider')]
    public function testResolve(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::resolve(1);
        $this->assertEquals(1, $promise->await());
    }

    #[DataProvider('handlerProvider')]
    public function testResolveThen(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::resolve(1);
        $this->assertEquals(3, $promise->then(fn($resolve) => $resolve + 2)->await());
    }

    #[DataProvider('handlerProvider')]
    public function testReject(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::reject(1);
        $this->assertEquals(1, $promise->await());
    }

    #[DataProvider('handlerProvider')]
    public function testRejectThen(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::reject(1);
        $this->assertEquals(6, $promise->then(fn($resolve) => $resolve + 2, fn($reject) => $reject + 5)->await());
    }

    #[DataProvider('handlerProvider')]
    public function testCatch(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('Error Message 2');
        });

        $result = $promise
            ->then(fn($resolve) => $resolve)
            ->catch(fn($reject) => $reject->getMessage())
            ->await();

        $this->assertEquals('Error Message 2', $result);
    }

    #[DataProvider('handlerProvider')]
    public function testFulfilledCatch(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $result = $promise
            ->then(fn($resolve) => $resolve)
            ->catch(fn($reject) => $reject->getMessage())
            ->await();

        $this->assertEquals(1, $result);
    }

    #[DataProvider('handlerProvider')]
    public function testFinally(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $result = $promise
            ->then(fn($resolve) => $resolve)
            ->catch(fn($reject) => $reject + 100)
            ->finally(fn() => 2)
            ->await();

        // finally's return value is ignored
        $this->assertEquals(1, $result);
    }

    #[DataProvider('handlerProvider')]
    public function testFinallyReject(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = new Promise(function ($resolve, $reject) {
            $reject(1);
        });

        $result = $promise
            ->then(fn($resolve) => $resolve)
            ->catch(fn($reject) => $reject + 100)
            ->finally(fn() => 2)
            ->await();

        // catch recovers with 101, finally passes it through
        $this->assertEquals(101, $result);
    }

    #[DataProvider('handlerProvider')]
    public function testCatchRecovers(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::reject(1)
            ->catch(fn($reason) => $reason + 100)
            ->then(fn($value) => $value + 10, fn($reason) => "Rejected: $reason");

        $this->assertEquals(111, $promise->await());
        $this->assertEquals(PromiseStatus::Fulfilled, $promise->getStatus());
    }

    #[DataProvider('handlerProvider')]
    public function testThenOnRejectedRecovers(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::reject(1)
            ->then(fn($value) => $value, fn($reason) => $reason + 5);

        $this->assertEquals(6, $promise->await());
        $this->assertEquals(PromiseStatus::Fulfilled, $promise->getStatus());
    }

    #[DataProvider('handlerProvider')]
    public function testCatchThrowingRejects(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::reject(1)
            ->catch(fn($reason) => throw new \Exception("Handler failed: $reason"));

        $result = $promise->await();
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
        $this->assertEquals('Handler failed: 1', $result->getMessage());
    }

    #[DataProvider('handlerProvider')]
    public function testFinallyPassesValueThrough(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::resolve(5)
            ->finally(function (...$args) {
                // The callback receives no argument
                if (count($args) !== 0) {
                    throw new \Exception('finally received ' . count($args) . ' argument(s)');
                }
                return 99;
            });

        $this->assertEquals(5, $promise->await());
        $this->assertEquals(PromiseStatus::Fulfilled, $promise->getStatus());
    }

    #[DataProvider('handlerProvider')]
    public function testFinallyKeepsRejection(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::reject(7)
            ->finally(fn() => 99);

        $this->assertEquals(7, $promise->await());
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
    }

    #[DataProvider('handlerProvider')]
    public function testErrorInHandlerRejects(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::resolve(5)
            ->then(fn($value) => $value->notAnObject());

        $result = $promise->await();
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
        $this->assertInstanceOf(\Error::class, $result);
    }

    #[DataProvider('handlerProvider')]
    public function testFinallyWithArgumentRejectsAndCatchRecovers(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        // finally() calls its callback with no argument: an old fn($value) throws ArgumentCountError
        $promise = Promise::resolve(5)
            ->finally(fn($value) => $value)
            ->catch(fn($error) => get_class($error));

        $this->assertEquals(\ArgumentCountError::class, $promise->await());
        $this->assertEquals(PromiseStatus::Fulfilled, $promise->getStatus());
    }

    #[DataProvider('handlerProvider')]
    public function testFinallyThrowingRejects(string $handlerClass)
    {
        Thread::useHandler($handlerClass);

        $promise = Promise::resolve(5)
            ->finally(fn() => throw new \Exception('Finally failed'));

        $result = $promise->await();
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
        $this->assertEquals('Finally failed', $result->getMessage());
    }
}
