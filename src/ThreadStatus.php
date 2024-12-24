<?php

namespace ByJG\PHPThread;

enum ThreadStatus
{
    case notStarted;
    case running;
    case finished;
    case error;
}
