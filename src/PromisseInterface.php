<?php

namespace ByJG\PHPThread;

use Closure;

interface PromisseInterface
{
    public function then(Closure $onFulfilled, Closure $onRejected = null): PromisseInterface;

    public function getPromisseStatus(): PromisseStatus;

    public function isFulfilled(): bool;

    public function await(): array;

}