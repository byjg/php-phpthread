<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ThreadInterface;

class Promisse implements PromisseInterface
{
    const STATUS_PENDING = 'pending';
    const STATUS_FULFILLED = 'fulfilled';
    const STATUS_REJECTED = 'rejected';

    protected \Closure $promisse;

    protected string $promisseStatus;

    protected ThreadInterface $promisseThread;
    protected ThreadInterface $promisseThen;

    protected array $promisseResultArgs = [];

    protected string $promisseId;

    public function __construct(\Closure $promisse)
    {
        $this->promisse = $promisse;
        $this->promisseStatus = self::STATUS_PENDING;

        $this->promisseId = uniqid("p_", true);

        $resolve = function () {
            SharedMemory::getInstance()->set($this->promisseId, func_get_args());
            SharedMemory::getInstance()->set($this->promisseId . "_st", self::STATUS_FULFILLED);
        };

        $reject = function () {
            SharedMemory::getInstance()->set($this->promisseId, func_get_args());
            SharedMemory::getInstance()->set($this->promisseId . "_st", self::STATUS_REJECTED);
        };

        $this->promisseThread = Thread::create($promisse);
        $this->promisseThread->execute($resolve, $reject);
        register_shutdown_function(function () {
            $this->checkPromisseState();
        });
    }

    protected function checkPromisseState($keep = false)
    {
        if ($this->promisseStatus !== self::STATUS_PENDING) {
            return $this->promisseStatus;
        }

        if ($this->promisseThread->getStatus() === Thread::STATUS_FINISHED) {
            $tempStatus = SharedMemory::getInstance()->get($this->promisseId . "_st");
            if (empty($tempStatus)) {
                return $this->promisseStatus;
            }
            $this->promisseStatus = SharedMemory::getInstance()->get($this->promisseId . "_st");
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

    public function getPromisseStatus(): string
    {
        $this->checkPromisseState();
        return $this->promisseStatus;
    }

    public function isFulfilled(): bool
    {
        $this->checkPromisseState();
        return $this->promisseStatus === self::STATUS_FULFILLED;
   }

   public function then(\Closure $onFulfilled, \Closure $onRejected = null): PromisseInterface
   {
       $then = function () use ($onFulfilled, $onRejected) {
           $this->promisseThread->waitFinish();
           $this->checkPromisseState(true);
           if ($this->promisseStatus === self::STATUS_FULFILLED) {
               $onFulfilled(...$this->promisseResultArgs);
           } else if ($this->promisseStatus === self::STATUS_REJECTED) {
               if ($onRejected) {
                   $onRejected(...$this->promisseResultArgs);
               }
           }
       };

       $this->checkPromisseState();
       if ($this->promisseStatus !== self::STATUS_PENDING) {
           $then();
       } else {
           $thread = Thread::create($then);
           $thread->execute();
       }

       return $this;
   }

   public function await()
   {
       $this->promisseThread->waitFinish();
       $this->checkPromisseState();
       return $this->promisseResultArgs;
   }

}