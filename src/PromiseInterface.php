<?php

namespace ByJG\PHPThread;

use Closure;

interface PromiseInterface
{
    public function then(Closure $onFulfilled, Closure $onRejected = null): PromiseInterface;

    public function getPromiseStatus(): PromiseStatus;

    public function isFulfilled(): bool;

    public function await(): array;

}