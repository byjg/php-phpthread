<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;

class Promise implements PromiseInterface
{
    protected Closure $promise;

    protected PromiseStatus $promiseStatus;

    protected ThreadInterface $promiseThread;
    protected Promise $parent;
    protected string $promiseId;

    public function __construct(Closure $promise)
    {
        $this->promise = $promise;
        $this->promiseStatus = PromiseStatus::pending;

        $this->promiseId = uniqid("p_", true);

        $resolve = function () {
            SharedMemory::getInstance()->set($this->promiseId, new PromiseResult(func_get_args(), PromiseStatus::fulfilled), 60);
        };

        $reject = function () {
            SharedMemory::getInstance()->set($this->promiseId, new PromiseResult(func_get_args(), PromiseStatus::rejected), 60);
        };

        $this->promiseThread = Thread::create($promise);
        $this->promiseThread->execute($resolve, $reject);
    }

    protected function getPromiseResult(): ?PromiseResult
    {
        return SharedMemory::getInstance()->get($this->promiseId);
    }

    public function getPromiseStatus(): PromiseStatus
    {
        if ($this->promiseThread->getStatus() === ThreadStatus::running) {
            return PromiseStatus::pending;
        }

        return $this->getPromiseResult()?->getStatus() ?? PromiseStatus::pending;
    }

    public function isFulfilled(): bool
    {
        return $this->getPromiseStatus() === PromiseStatus::fulfilled;
   }

    public function then(Closure $onFulfilled, Closure $onRejected = null): PromiseInterface
   {
       $promise = $this;
       $then = function () use ($onFulfilled, $onRejected, $promise) {
           $status = $promise->getPromiseStatus();
           while ($status === PromiseStatus::pending) {
               usleep(100);
               $status = $promise->getPromiseStatus();
           }
           $args = $promise->getPromiseResult()->getResult();
           if ($status === PromiseStatus::fulfilled) {
               $onFulfilled(...$args);
           } else if ($status === PromiseStatus::rejected) {
               if ($onRejected) {
                   $onRejected(...$args);
               }
           }
       };

       if ($this->getPromiseStatus() !== PromiseStatus::pending) {
           $then();
       } else {
           $thread = Thread::create($then);
           $thread->execute();
       }

       return $this;
   }

    public function await(): array
   {
       $this->promiseThread->waitFinish();
       $this->getPromiseStatus();
       return $this->getPromiseResult()->getResult();
   }
}