<?php

namespace ByJG\PHPThread;


class PromisseResult
{
    protected mixed $result;

    protected PromisseStatus $status;

    public function __construct(mixed $result, PromisseStatus $status)
    {
        $this->result = $result;
        $this->status = $status;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function getStatus(): PromisseStatus
    {
        return $this->status;
    }
}