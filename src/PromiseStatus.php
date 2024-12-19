<?php

namespace ByJG\PHPThread;

enum PromiseStatus: string
{
    case pending = 'pending';
    case fulfilled = 'fulfilled';
    case rejected = 'rejected';
}
