<?php

namespace Tests;

use ByJG\PHPThread\Promise;
use ByJG\PHPThread\PromiseStatus;
use PHPUnit\Framework\TestCase;

class PromiseTest extends TestCase
{
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
        Promise::gc();
    }

    public function testPromiseFulfilled()
    {
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

    public function testPromiseFulfilledSequence()
    {
        $result = Promise::create(function ($resolve, $reject) {
            $resolve(1);
        })
            ->then(fn($resolve) => $resolve + 2)
            ->then(fn($resolve) => $resolve + 10)
            ->await();

        $this->assertEquals(13, $result);
    }

    public function testPromiseRejected()
    {
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

    public function testPromiseRejectedSequence()
    {
        $result = Promise::create(function ($resolve, $reject) {
            $reject(1);
        })
            ->then(fn($resolve) => $resolve + 2, fn($reject) => $reject + 5)
            ->then(fn($resolve) => $resolve + 10, fn($reject) => $reject + 2)
            ->await();

        // The first onRejected handles the rejection, so the chain continues fulfilled: 1 + 5 + 10
        $this->assertEquals(16, $result);
    }

    public function testAllFulfilled()
    {
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

    public function testAllReject()
    {
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

    public function testAllFulfilledCatch()
    {
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

    public function testAllRejectCatch()
    {
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

    public function testRaceFulfilled()
    {
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

        $this->assertEquals(2, $result);

    }

    public function testPromiseException()
    {
        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('Error Message');
        });

        $result = $promise->await();

        $this->assertEquals('Error Message', $result->getMessage());
    }

    public function testPromiseException2()
    {
        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('Error Message 2');
        });

        $result = $promise->then(
            fn($resolve) => $resolve,
            fn($reject) => $reject->getMessage()
        )->await();

        $this->assertEquals('Error Message 2', $result);
    }

    public function testPromiseException3()
    {
        $promise = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $result = $promise->then(
            fn($resolve) => throw new \Exception('Error Message 3'),
        )->await();

        $this->assertEquals('Error Message 3', $result->getMessage());
    }

    public function testResolve()
    {
        $promise = Promise::resolve(1);
        $this->assertEquals(1, $promise->await());
    }

    public function testResolveThen()
    {
        $promise = Promise::resolve(1);
        $this->assertEquals(3, $promise->then(fn($resolve) => $resolve + 2)->await());
    }

    public function testReject()
    {
        $promise = Promise::reject(1);
        $this->assertEquals(1, $promise->await());
    }

    public function testRejectThen()
    {
        $promise = Promise::reject(1);
        $this->assertEquals(6, $promise->then(fn($resolve) => $resolve + 2, fn($reject) => $reject + 5)->await());
    }

    public function testCatch()
    {
        $promise = new Promise(function ($resolve, $reject) {
            throw new \Exception('Error Message 2');
        });

        $result = $promise
            ->then(fn($resolve) => $resolve)
            ->catch(fn($reject) => $reject->getMessage())
            ->await();

        $this->assertEquals('Error Message 2', $result);
    }

    public function testFulfilledCatch()
    {
        $promise = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        $result = $promise
            ->then(fn($resolve) => $resolve)
            ->catch(fn($reject) => $reject->getMessage())
            ->await();

        $this->assertEquals(1, $result);
    }

    public function testFinally()
    {
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

    public function testFinallyReject()
    {
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

    public function testCatchRecovers()
    {
        $promise = Promise::reject(1)
            ->catch(fn($reason) => $reason + 100)
            ->then(fn($value) => $value + 10, fn($reason) => "Rejected: $reason");

        $this->assertEquals(111, $promise->await());
        $this->assertEquals(PromiseStatus::Fulfilled, $promise->getStatus());
    }

    public function testThenOnRejectedRecovers()
    {
        $promise = Promise::reject(1)
            ->then(fn($value) => $value, fn($reason) => $reason + 5);

        $this->assertEquals(6, $promise->await());
        $this->assertEquals(PromiseStatus::Fulfilled, $promise->getStatus());
    }

    public function testCatchThrowingRejects()
    {
        $promise = Promise::reject(1)
            ->catch(fn($reason) => throw new \Exception("Handler failed: $reason"));

        $result = $promise->await();
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
        $this->assertEquals('Handler failed: 1', $result->getMessage());
    }

    public function testFinallyPassesValueThrough()
    {
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

    public function testFinallyKeepsRejection()
    {
        $promise = Promise::reject(7)
            ->finally(fn() => 99);

        $this->assertEquals(7, $promise->await());
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
    }

    public function testErrorInHandlerRejects()
    {
        $promise = Promise::resolve(5)
            ->then(fn($value) => $value->notAnObject());

        $result = $promise->await();
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
        $this->assertInstanceOf(\Error::class, $result);
    }

    public function testFinallyWithArgumentRejectsAndCatchRecovers()
    {
        // finally() calls its callback with no argument: an old fn($value) throws ArgumentCountError
        $promise = Promise::resolve(5)
            ->finally(fn($value) => $value)
            ->catch(fn($error) => get_class($error));

        $this->assertEquals(\ArgumentCountError::class, $promise->await());
        $this->assertEquals(PromiseStatus::Fulfilled, $promise->getStatus());
    }

    public function testFinallyThrowingRejects()
    {
        $promise = Promise::resolve(5)
            ->finally(fn() => throw new \Exception('Finally failed'));

        $result = $promise->await();
        $this->assertEquals(PromiseStatus::Rejected, $promise->getStatus());
        $this->assertEquals('Finally failed', $result->getMessage());
    }
}
