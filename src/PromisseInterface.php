<?php

namespace ByJG\PHPThread;

interface PromisseInterface
{
    public function then(\Closure $onFulfilled, \Closure $onRejected = null);

    public function getPromisseStatus(): string;

    public function isFulfilled(): bool;

    public function await();

}