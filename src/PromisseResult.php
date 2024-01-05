<?php

// @todo: Need implement this class

//namespace ByJG\PHPThread;
//
//
//use ByJG\PHPThread\Handler\ThreadInterface;
//
//class PromisseResult implements \ByJG\PHPThread\PromisseInterface
//{
//    protected ThreadInterface $thread;
//
//    public function __construct(\Closure $promisse)
//    {
//        $this->thread = Thread::create($promisse);
//        $this->thread->execute();
//    }
//
//    public function then(\Closure $onFulfilled, \Closure $onRejected = null): PromisseInterface
//    {
//        throw new \RuntimeException("Not implemented chain of promisses");
//    }
//
//    public function getPromisseStatus(): string
//    {
//        switch ($this->thread->getStatus()) {
//            case Thread::STATUS_FINISHED:
//                return Promisse::STATUS_FULFILLED;
//            case Thread::STATUS_RUNNING:
//                return Promisse::STATUS_PENDING;
//            case Thread::STATUS_ERROR:
//                return Promisse::STATUS_REJECTED;
//        }
//
//        return Promisse::STATUS_PENDING;
//    }
//
//    public function isFulfilled(): bool
//    {
//        return $this->getPromisseStatus() === Promisse::STATUS_FULFILLED;
//    }
//
//    public function await()
//    {
//        $this->thread->waitFinish();
//        return $this->thread->getResult();
//    }
//}