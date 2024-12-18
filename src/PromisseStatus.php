<?php

namespace ByJG\PHPThread;

enum PromisseStatus: string
{
    case pending = 'pending';
    case fulfilled = 'fulfilled';
    case rejected = 'rejected';
}
