<?php

namespace ByJG\PHPThread;

enum ThreadStatus
{
    case NotStarted;
    case Running;
    case Finished;
    case Error;
}
