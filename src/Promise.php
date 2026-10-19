<?php

namespace ByJG\PHPThread;

use ByJG\Cache\Exception\InvalidArgumentException;
use ByJG\PHPThread\Handler\ThreadInterface;
use Closure;
use Throwable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Promise implements PromiseInterface
{
    protected Closure $promise;

    protected ThreadInterface $thread;

    protected ?PromiseResult $result = null;
    protected string $promiseId;

    public function __construct(Closure $executor)
    {
        SharedMemory::getInstance();
        $this->promiseId = "p_" . bin2hex(random_bytes(16));

        $fn = function () use ($executor) {
            $resolve = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::Fulfilled));
            };

            $reject = function ($value = null) {
                SharedMemory::getInstance()->set($this->promiseId, new PromiseResult($value, PromiseStatus::Rejected));
            };

            try {
                $executor($resolve, $reject);
            } catch (Throwable $ex) {
                // An Error (TypeError, ArgumentCountError, ...) rejects the promise too, as in JavaScript
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
     * @return PromiseResult|null
     * @throws InvalidArgumentException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[\Override]
    public function getResult(): ?PromiseResult
    {
        if (!empty($this->result)) {
            return $this->result;
        }

        $result = SharedMemory::getInstance()->get($this->promiseId);
        if (!empty($result)) {
            $this->result = $result;
        } else {
            return null;
        }

        return $this->result;
    }

    /**
     * @inheritDoc
     * @return PromiseStatus
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     */
    #[\Override]
    public function getStatus(): PromiseStatus
    {
        if ($this->thread->getStatus() === ThreadStatus::Running) {
            return PromiseStatus::Pending;
        }

        return $this->getResult()?->getStatus() ?? PromiseStatus::Pending;
    }

    /**
     * @inheritDoc
     * @return bool
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     */
    #[\Override]
    public function isFulfilled(): bool
    {
        return $this->getStatus() === PromiseStatus::Fulfilled;
    }

    /**
     * Wait until this promise settles and return its result.
     */
    protected function waitResult(): PromiseResult
    {
        $status = $this->getStatus();
        while ($status === PromiseStatus::Pending) {
            $status = $this->getStatus();
        }
        $result = $this->getResult();
        if ($result === null) {
            throw new \RuntimeException('Promise result is null');
        }
        return $result;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function then(Closure $onFulfilled, ?Closure $onRejected = null): PromiseInterface
    {
        $then = function ($resolve, $reject) use ($onFulfilled, $onRejected) {
            $result = $this->waitResult();
            $args = $result->getResult();
            if ($result->getStatus() === PromiseStatus::Fulfilled) {
                $resolve($onFulfilled($args));
            } else if ($onRejected) {
                // As in JavaScript, a handled rejection resolves the returned promise
                $resolve($onRejected($args));
            } else {
                $reject($args);
            }
        };

        return new Promise($then);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function catch(Closure $onRejected): PromiseInterface
    {
        return $this->then(fn($value) => $value, $onRejected);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function finally(Closure $onFinally): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($onFinally) {
            $result = $this->waitResult();
            // As in JavaScript, the callback gets no argument and its return value is ignored:
            // the returned promise settles with the original value or reason
            $onFinally();
            if ($result->getStatus() === PromiseStatus::Fulfilled) {
                $resolve($result->getResult());
            } else {
                $reject($result->getResult());
            }
        });
    }

    /**
     * @inheritDoc
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws InvalidArgumentException
     * @throws NotFoundExceptionInterface
     */
    #[\Override]
    public function await(): mixed
    {
        $this->thread->join();
        $x = $this->getResult();
        if (is_null($x)) {
            throw new \RuntimeException('Promise result is null for promise: ' . $this->getPromiseId());
        }
        return $x->getResult();
    }

    public static function all(PromiseInterface ...$promises): PromiseInterface
    {
        return new Promise(function ($resolve, $reject) use ($promises) {
            $results = [];
            while (count($promises) > 0) {
                foreach ($promises as $key => $promise) {
                    if ($promise->getStatus() === PromiseStatus::Rejected) {
                        $result = $promise->getResult();
                        if ($result === null) {
                            throw new \RuntimeException('Promise result is null');
                        }
                        $reject($result->getResult());
                        return;
                    }
                    if ($promise->getStatus() === PromiseStatus::Fulfilled) {
                        $result = $promise->getResult();
                        if ($result === null) {
                            throw new \RuntimeException('Promise result is null');
                        }
                        $results[] = $result->getResult();
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
                foreach ($promises as $promise) {
                    if ($promise->getStatus() === PromiseStatus::Rejected) {
                        $result = $promise->getResult();
                        if ($result === null) {
                            throw new \RuntimeException('Promise result is null');
                        }
                        $reject($result->getResult());
                        return;
                    }
                    if ($promise->getStatus() === PromiseStatus::Fulfilled) {
                        $result = $promise->getResult();
                        if ($result === null) {
                            throw new \RuntimeException('Promise result is null');
                        }
                        $resolve($result->getResult());
                        return;
                    }
                }
            }
        });
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws InvalidArgumentException
     */
    public static function gc(): void
    {
        SharedMemory::getInstance()->clear();
    }
}
