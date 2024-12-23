<?php

namespace ByJG\PHPThread;

use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;

class Promise implements PromiseInterface
{
    protected Closure $promise;

    protected ThreadInterface $thread;

    protected PromiseResult $result;
    protected string $promiseId;

    public function __construct(Closure $executor)
    {
        SharedMemory::getInstance();
        $this->promiseId = "p_" . bin2hex(random_bytes(16));

        $fn = function () use ($executor) {
            $resolve = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::fulfilled));
            };

            $reject = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::rejected));
            };

            try {
                $executor($resolve, $reject);
            } catch (\Exception $ex) {
                $reject($ex);
            }
        };

        $this->thread = Thread::create($fn);
        $this->thread->start();
    }

    public function getPromiseId(): string
    {
        return $this->promiseId;
    }

    public static function create(Closure $promise): PromiseInterface
    {
        return new Promise($promise);
    }

    public static function resolve($value): PromiseInterface
    {
        return new Promise(function ($resolve) use ($value) {
            $resolve($value);
        });
    }

    public static function reject($value): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($value) {
            $reject($value);
        });
    }

    /**
     * @inheritDoc
     */
    public function getResult(): ?PromiseResult
    {
        if (!empty($this->result)) {
            return $this->result;
        }

        $result = SharedMemory::getInstance()->get($this->promiseId);
        if (!empty($result)) {
            $this->result = $result;
//            SharedMemory::getInstance()->delete($this->promiseId);
        }

        if (!isset($this->result)) {
            return null;
        }

        return $this->result;
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): PromiseStatus
    {
        if ($this->thread->getStatus() === ThreadStatus::running) {
            return PromiseStatus::pending;
        }

        return $this->getResult()?->getStatus() ?? PromiseStatus::pending;
    }

    /**
     * @inheritDoc
     */
    public function isFulfilled(): bool
    {
        return $this->getStatus() === PromiseStatus::fulfilled;
    }

    /**
     * @inheritDoc
     */
    public function then(Closure $onFulfilled, Closure $onRejected = null): PromiseInterface
    {
        $promise = $this;
        $then = function ($resolve, $reject) use ($onFulfilled, $onRejected, $promise) {
            $status = $promise->getStatus();
            while ($status === PromiseStatus::pending) {
                $status = $promise->getStatus();
            }
            $args = $promise->getResult()->getResult();
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

    /**
     * @inheritDoc
     */
    public function await(): mixed
    {
        $this->thread->join();
        $x = $this->getResult();
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
                    if ($promise->getStatus() === PromiseStatus::rejected) {
                        $reject($promise->getResult()->getResult());
                        return;
                    }
                    if ($promise->getStatus() === PromiseStatus::fulfilled) {
                        $results[] = $promise->getResult()->getResult();
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
                    if ($promise->getStatus() === PromiseStatus::rejected) {
                        $reject($promise->getResult()->getResult());
                        return;
                    }
                    if ($promise->getStatus() === PromiseStatus::fulfilled) {
                        $resolve($promise->getResult()->getResult());
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