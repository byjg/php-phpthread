<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;

class Promise implements PromiseInterface
{
    protected Closure $promise;

    protected ThreadInterface $promiseThread;

    protected PromiseResult $promiseResult;
    protected string $promiseId;

    public function __construct(Closure $promise)
    {
        SharedMemory::getInstance();
        $this->promiseId = "p_" . bin2hex(random_bytes(16));

        $fn = function () use ($promise) {
            $resolve = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::fulfilled));
            };

            $reject = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::rejected));
            };

            try {
                $promise($resolve, $reject);
            } catch (\Exception $ex) {
                $reject($ex);
            }
        };

        $this->promiseThread = Thread::create($fn);
        $this->promiseThread->execute();
    }

    public function getPromiseId(): string
    {
        return $this->promiseId;
    }

    public static function create(Closure $promise): PromiseInterface
    {
        return new Promise($promise);
    }

    public function getPromiseResult(): ?PromiseResult
    {
        if (!empty($this->promiseResult)) {
            return $this->promiseResult;
        }

        $result = SharedMemory::getInstance()->get($this->promiseId);
        if (!empty($result)) {
            $this->promiseResult = $result;
//            SharedMemory::getInstance()->delete($this->promiseId);
        }

        if (!isset($this->promiseResult)) {
            return null;
        }

        return $this->promiseResult;
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
        $then = function ($resolve, $reject) use ($onFulfilled, $onRejected, $promise) {
            $status = $promise->getPromiseStatus();
            while ($status === PromiseStatus::pending) {
                $status = $promise->getPromiseStatus();
            }
            $args = $promise->getPromiseResult()->getResult();
            if ($status === PromiseStatus::fulfilled) {
                $resolve($onFulfilled($args));
            } else if ($status === PromiseStatus::rejected) {
                if ($onRejected) {
                    $reject($onRejected($args));
                }
            }
        };

//        while ($this->getPromiseStatus() === PromiseStatus::pending) {
//            $this->promiseThread->waitFinish();
//        }

        return new Promise($then);
    }

    public function await(): mixed
    {
        $this->promiseThread->waitFinish();
        $x = $this->getPromiseResult();
        if (is_null($x)) {
            echo $this->getPromiseId() . "\n";
        }
        return $x->getResult();
    }

    public static function all(PromiseInterface ...$promises): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($promises) {
            $results = [];
            while (count($promises) > 0) {
                foreach ($promises as $key => $promise) {
                    if ($promise->getPromiseStatus() === PromiseStatus::rejected) {
                        $reject($promise->getPromiseResult()->getResult());
                        return;
                    }
                    if ($promise->getPromiseStatus() === PromiseStatus::fulfilled) {
                        $results[] = $promise->getPromiseResult()->getResult();
                        unset($promises[$key]);
                    }
                }
            }
            $resolve($results);
        });
    }

    public static function race(PromiseInterface ...$promises): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($promises) {
            while (count($promises) > 0) {
                foreach ($promises as $key => $promise) {
                    if ($promise->getPromiseStatus() === PromiseStatus::rejected) {
                        $reject($promise->getPromiseResult()->getResult());
                        return;
                    }
                    if ($promise->getPromiseStatus() === PromiseStatus::fulfilled) {
                        $resolve($promise->getPromiseResult()->getResult());
                        return;
                    }
                }
            }
        });
    }

    public static function gc()
    {
        SharedMemory::getInstance()->clear();
    }
}