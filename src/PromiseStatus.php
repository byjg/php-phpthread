<?php

namespace ByJG\PHPThread;

enum PromiseStatus: string
{
    case Pending = 'pending';
    case Fulfilled = 'fulfilled';
    case Rejected = 'rejected';
}
