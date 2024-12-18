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

    protected array $promisseResultArgs = [];

    protected string $promisseId;

    public function __construct(Closure $promisse)
    {
        $this->promisse = $promisse;
        $this->promisseStatus = PromisseStatus::pending;

        $this->promisseId = uniqid("p_", true);

        $resolve = function () {
            SharedMemory::getInstance()->set($this->promisseId, func_get_args());
            SharedMemory::getInstance()->set($this->promisseId . "_st", PromisseStatus::fulfilled);
        };

        $reject = function () {
            SharedMemory::getInstance()->set($this->promisseId, func_get_args());
            SharedMemory::getInstance()->set($this->promisseId . "_st", PromisseStatus::rejected);
        };

        $this->promisseThread = Thread::create($promisse);
        $this->promisseThread->execute($resolve, $reject);
        register_shutdown_function(function () {
            $this->checkPromisseState();
        });
    }

    protected function checkPromisseState(bool $keep = false): PromisseStatus
    {
        if ($this->promisseStatus !== PromisseStatus::pending) {
            return $this->promisseStatus;
        }

        if ($this->promisseThread->getStatus() === ThreadStatus::finished) {
            $tempStatus = SharedMemory::getInstance()->get($this->promisseId . "_st");
            if (empty($tempStatus)) {
                return $this->promisseStatus;
            }
            $this->promisseStatus = $tempStatus;
            $this->promisseResultArgs = SharedMemory::getInstance()->get($this->promisseId);
            if (!$keep) {
                SharedMemory::getInstance()->delete($this->promisseId);
                SharedMemory::getInstance()->delete($this->promisseId . "_st");
            }
        }

        return $this->promisseStatus;
    }

    protected function getPromisseResultArgs($keep = false): array
    {
        $this->checkPromisseState($keep);
        return $this->promisseResultArgs;
    }

    public function getPromisseStatus(): PromisseStatus
    {
        $this->checkPromisseState();
        return $this->promisseStatus;
    }

    public function isFulfilled(): bool
    {
        $this->checkPromisseState();
        return $this->promisseStatus === PromisseStatus::fulfilled;
   }

    public function then(Closure $onFulfilled, Closure $onRejected = null): PromisseInterface
   {
       // Because of the Thread, we need to set the $this in a variable
       $promisse = clone $this;
       $promisse->parent = $this;

       $then = function () use ($onFulfilled, $onRejected, $promisse) {
           echo "class: " . get_class($promisse->promisseThread) . "\n";
           $promisse->promisseThread->waitFinish();
           $status = $promisse->checkPromisseState(true);
           while ($status === PromisseStatus::pending) {
               usleep(100);
               $status = $promisse->checkPromisseState(true);
           }
           $promisse->parent->promisseStatus = $status;
           if ($status === PromisseStatus::fulfilled) {
               $onFulfilled(...$promisse->promisseResultArgs);
           } else if ($status === PromisseStatus::rejected) {
               if ($onRejected) {
                   $onRejected(...$promisse->promisseResultArgs);
               }
           }
       };

       $this->checkPromisseState();
       if ($this->promisseStatus !== PromisseStatus::pending) {
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
       $this->checkPromisseState();
       return $this->promisseResultArgs;
   }

}