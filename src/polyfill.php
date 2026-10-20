<?php

// The signal constants come from ext-pcntl, which is optional: ThreadInterface::terminate() uses SIGKILL
// as its default and ProcessHandler::terminate() needs the signal numbers without pcntl
if (!defined('SIGKILL')) {
    define('SIGKILL', 9);
}
if (!defined('SIGTERM')) {
    define('SIGTERM', 15);
}
