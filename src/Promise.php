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

        $this->promiseId = "p_" . bin2hex(random_bytes(16));

        $fn = function () use ($promise) {
            $resolve = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::fulfilled), 60);
            };

            $reject = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::rejected), 60);
            };

            try {
                $promise($resolve, $reject);
            } catch (\Exception $ex) {
                $reject($ex);
            }
        };

        echo "create: {$this->promiseId} \n";
        $this->promiseThread = Thread::create($fn);
        $this->promiseThread->execute();

    }

    public function __destruct()
    {
        $result = $this->promiseThread->isAlive() ? "true" : "false";
        echo "Destructing Promise {$this->promiseId} - {$result} \n";
        //SharedMemory::getInstance()->delete($this->promiseId);
    }

    public static function create(Closure $promise): PromiseInterface
    {
        return new Promise($promise);
    }

    public function getPromiseResult(): ?PromiseResult
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

        return new Promise($then);
    }

    public function await(): mixed
    {
        $this->promiseThread->waitFinish();
        $this->getPromiseStatus();
        return $this->getPromiseResult()->getResult();
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
}