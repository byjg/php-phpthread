<?php

namespace ByJG\PHPThread;


class PromiseResult
{
    protected mixed $result;

    protected PromiseStatus $status;

    public function __construct(mixed $result, PromiseStatus $status)
    {
        $this->result = $result;
        $this->status = $status;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getStatus(): PromiseStatus
    {
        return $this->status;
    }
}