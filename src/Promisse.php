<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;

class Promisse implements PromisseInterface
{
    protected Closure $promisse;

    protected PromisseStatus $promisseStatus;

    protected ThreadInterface $promisseThread;
    protected Promisse $parent;
    protected string $promisseId;

    public function __construct(Closure $promisse)
    {
        $this->promisse = $promisse;
        $this->promisseStatus = PromisseStatus::pending;

        $this->promisseId = uniqid("p_", true);

        $resolve = function () {
            SharedMemory::getInstance()->set($this->promisseId, new PromisseResult(func_get_args(), PromisseStatus::fulfilled), 60);
        };

        $reject = function () {
            SharedMemory::getInstance()->set($this->promisseId, new PromisseResult(func_get_args(), PromisseStatus::rejected), 60);
        };

        $this->promisseThread = Thread::create($promisse);
        $this->promisseThread->execute($resolve, $reject);
    }

    protected function getPromisseResult(): ?PromisseResult
    {
        return SharedMemory::getInstance()->get($this->promisseId);
    }

    public function getPromisseStatus(): PromisseStatus
    {
        if ($this->promisseThread->getStatus() === ThreadStatus::running) {
            return PromisseStatus::pending;
        }

        return $this->getPromisseResult()?->getStatus() ?? PromisseStatus::pending;
    }

    public function isFulfilled(): bool
    {
        return $this->getPromisseStatus() === PromisseStatus::fulfilled;
   }

    public function then(Closure $onFulfilled, Closure $onRejected = null): PromisseInterface
   {
       $promisse = $this;
       $then = function () use ($onFulfilled, $onRejected, $promisse) {
           $status = $promisse->getPromisseStatus();
           while ($status === PromisseStatus::pending) {
               usleep(100);
               $status = $promisse->getPromisseStatus();
           }
           $args = $promisse->getPromisseResult()->getResult();
           if ($status === PromisseStatus::fulfilled) {
               $onFulfilled(...$args);
           } else if ($status === PromisseStatus::rejected) {
               if ($onRejected) {
                   $onRejected(...$args);
               }
           }
       };

       if ($this->getPromisseStatus() !== PromisseStatus::pending) {
           $then();
       } else {
           $thread = Thread::create($then);
           $thread->execute();
       }

       return $this;
   }

    public function await(): array
   {
       $this->promisseThread->waitFinish();
       $this->getPromisseStatus();
       return $this->getPromisseResult()->getResult();
   }
}