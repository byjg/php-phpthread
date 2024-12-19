<?php


use ByJG\PHPThread\Promise;
use ByJG\PHPThread\PromiseStatus;
use PHPUnit\Framework\TestCase;

class PromiseTest extends TestCase
{
    public function testPromiseFulfilled()
    {
        echo "A\n";
        $x = new Promise(function ($resolve, $reject) {
            $resolve(1);
        });

        echo "B\n";

        // Test if the promise is pending
        $this->assertEquals(1, $x->await());

        echo "C\n";

        // Test if the promise is fulfilled
        $this->assertEquals(PromiseStatus::fulfilled, $x->getPromiseStatus());

        echo "D\n";

        // Test then
        $this->assertEquals(3, $x->then(fn($resolve) => $resolve + 2)->await());
        $this->assertEquals(11, $x->then(fn($resolve) => $resolve + 10)->await());

        echo "E\n";
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
        $this->assertEquals(PromiseStatus::rejected, $x->getPromiseStatus());

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

        $this->assertEquals(8, $result);
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
}
