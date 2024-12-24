<?php

namespace ByJG\PHPThread;

use Closure;

interface PromiseInterface
{
    /**
     * Add fulfillment and rejection handlers to the promise.
     *
     * @param Closure $onFulfilled The callback to execute if the promise is fulfilled.
     * @param Closure|null $onRejected The callback to execute if the promise is rejected.
     * @return PromiseInterface A new promise for chaining further actions.
     */
    public function then(Closure $onFulfilled, Closure $onRejected = null): PromiseInterface;

    /**
     * Add a rejection handler to the promise.
     *
     * @param Closure $onRejected The callback to execute if the promise is rejected.
     * @return PromiseInterface A new promise for chaining further actions.
     */
    public function catch(Closure $onRejected): PromiseInterface;

    /**
     * Add a final handler to the promise.
     *
     * @param Closure $onFinally The callback to execute when the promise is settled.
     * @return PromiseInterface A new promise for chaining further actions.
     */
    public function finally(Closure $onFinally): PromiseInterface;

    /**
     * Retrieve the current status of the promise.
     *
     * @return PromiseStatus The status of the promise (e.g., PENDING, FULFILLED, REJECTED).
     */
    public function getStatus(): PromiseStatus;

    /**
     * Retrieve the result of the promise.
     *
     * @return PromiseResult|null The result if the promise is fulfilled, or null otherwise.
     */
    public function getResult(): ?PromiseResult;

    /**
     * Check if the promise has been fulfilled.
     *
     * @return bool True if the promise is fulfilled, false otherwise.
     */
    public function isFulfilled(): bool;

    /**
     * Wait for the promise to resolve and retrieve its result.
     *
     * @return mixed The resolved value of the promise.
     */
    public function await(): mixed;

}