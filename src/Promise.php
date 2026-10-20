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

    /**
     * The process that started the thread: only it can tell that the thread ended
     * (a forked or a child process has a copy of this promise)
     */
    protected int $ownerPid;

    /**
     * The promises this one waits for (then, catch, finally, all, race)
     * @var PromiseInterface[]
     */
    protected array $dependencies = [];

    public function __construct(Closure $executor)
    {
        SharedMemory::getInstance();
        $this->promiseId = "p_" . bin2hex(random_bytes(16));
        $this->ownerPid = (int)getmypid();

        // The id is captured by value: ProcessHandler serializes this closure, and laravel/serializable-closure
        // does not bind $this when it is used only inside a nested closure
        $promiseId = $this->promiseId;
        $fn = function () use ($executor, $promiseId) {
            $resolve = function ($value = null) use ($promiseId) {
                if ($value instanceof PromiseInterface) {
                    // A promise is followed, as in JavaScript: a promise cannot be shared between threads,
                    // so this thread waits for it and settles with its value or reason
                    $value = new PromiseResult($value->await(), $value->getStatus());
                } else {
                    $value = new PromiseResult($value, PromiseStatus::Fulfilled);
                }
                SharedMemory::getInstance()->set($promiseId, $value);
            };

            $reject = function ($value = null) use ($promiseId) {
                SharedMemory::getInstance()->set($promiseId, new PromiseResult($value, PromiseStatus::Rejected));
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

    /**
     * Run the closure with the arguments in a thread: the promise is fulfilled with its return value
     * (a returned promise is followed) or rejected with what it throws, as the JavaScript Promise.try().
     * The arguments and the return value must be serializable.
     */
    public static function try(Closure $closure, mixed ...$args): PromiseInterface
    {
        return new Promise(function ($resolve) use ($closure, $args) {
            $resolve($closure(...$args));
        });
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

        // Checked before reading the result: a thread that settles the promise and ends in between
        // must not be taken as one that ended without settling it
        $threadEnded = $this->ownerPid === getmypid() && $this->thread->getStatus() !== ThreadStatus::Running;

        $result = SharedMemory::getInstance()->get($this->promiseId);
        if (!empty($result)) {
            $this->result = $result;
        } elseif ($threadEnded) {
            // The thread died (fatal error, exit, killed) or the executor never called $resolve or $reject:
            // nothing can settle the promise anymore. The rejection is shared with the promises chained in
            // other processes, which wait for this result.
            $this->result = new PromiseResult($this->threadEndedReason(), PromiseStatus::Rejected);
            SharedMemory::getInstance()->set($this->promiseId, $this->result);
        }

        return $this->result;
    }

    /**
     * The reason of a thread that ended without settling the promise
     */
    protected function threadEndedReason(): Throwable
    {
        try {
            // e.g. ProcessHandler: "The PHP process ended without a result (exit code 255)"
            $this->thread->getResult();
        } catch (Throwable $ex) {
            return $ex;
        }

        return new \RuntimeException('The thread ended without settling the promise ' . $this->promiseId);
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
            // The thread may wait for a promise whose thread ended without settling it,
            // which only this process can reject
            foreach ($this->dependencies as $dependency) {
                $dependency->getStatus();
            }
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
        while ($this->getStatus() === PromiseStatus::Pending) {
            usleep(1000);
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

        return self::waitingFor(new Promise($then), [$this]);
    }

    /**
     * @param PromiseInterface[] $dependencies
     */
    protected static function waitingFor(Promise $promise, array $dependencies): Promise
    {
        $promise->dependencies = $dependencies;
        return $promise;
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
        return self::waitingFor(new Promise(function ($resolve, $reject) use ($onFinally) {
            $result = $this->waitResult();
            // As in JavaScript, the callback gets no argument and its return value is ignored:
            // the returned promise settles with the original value or reason
            $onFinally();
            if ($result->getStatus() === PromiseStatus::Fulfilled) {
                $resolve($result->getResult());
            } else {
                $reject($result->getResult());
            }
        }), [$this]);
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
        return $this->waitResult()->getResult();
    }

    public static function all(PromiseInterface ...$promises): PromiseInterface
    {
        return self::waitingFor(new Promise(function ($resolve, $reject) use ($promises) {
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
                        $results[$key] = $result->getResult();
                        unset($promises[$key]);
                    }
                }
            }
            // The results follow the order of the promises, not the order they settle
            ksort($results);
            $resolve($results);
        }), $promises);
    }

    /**
     * Run the callback with each item and key in threads, at most $concurrency at a time: the next item starts when
     * one settles (e.g. one thread per CPU core). Fulfilled with the values in the order (and keys) of the items,
     * or rejected with the first rejection, after which no other item starts.
     *
     * The threads are managed by the thread of the returned promise. The items, the callback and the values must be
     * serializable, so the items are read at once (a generator is converted to an array).
     *
     * @param iterable $items
     * @param Closure $callback fn($item, $key), run in a thread (a returned promise is followed)
     */
    public static function map(iterable $items, Closure $callback, int $concurrency): PromiseInterface
    {
        if ($concurrency < 1) {
            throw new \InvalidArgumentException('The concurrency must be at least 1');
        }
        $items = is_array($items) ? $items : iterator_to_array($items);

        return new Promise(function ($resolve, $reject) use ($items, $callback, $concurrency) {
            $keys = array_keys($items);
            $next = 0;
            $running = [];
            $values = [];

            while ($next < count($keys) || count($running) > 0) {
                while (count($running) < $concurrency && $next < count($keys)) {
                    $key = $keys[$next++];
                    $running[$key] = Promise::try($callback, $items[$key], $key);
                }

                foreach ($running as $key => $promise) {
                    $status = $promise->getStatus();
                    if ($status === PromiseStatus::Pending) {
                        continue;
                    }
                    $result = $promise->getResult();
                    if ($result === null) {
                        throw new \RuntimeException('Promise result is null');
                    }
                    if ($status === PromiseStatus::Rejected) {
                        $reject($result->getResult());
                        return;
                    }
                    $values[$key] = $result->getResult();
                    unset($running[$key]);
                }

                if (count($running) >= $concurrency || ($next >= count($keys) && count($running) > 0)) {
                    usleep(1000);
                }
            }

            // The values follow the order of the items, not the order they settle
            $resolve(array_replace(array_fill_keys($keys, null), $values));
        });
    }

    public static function race(PromiseInterface ...$promises): PromiseInterface
    {
        return self::waitingFor(new Promise(function ($resolve, $reject) use ($promises) {
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
        }), $promises);
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
