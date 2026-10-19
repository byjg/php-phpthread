<?php

/**
 * The child process of ByJG\PHPThread\Handler\ProcessHandler.
 *
 * Usage: php process-worker.php <path of the Composer vendor/autoload.php>
 *
 * Reads the serialized [closure, arguments, onFinish, shared memory prefix] from STDIN, runs the closure
 * and writes the serialized result, or the Throwable it threw, to the file descriptor 3.
 */

use ByJG\PHPThread\SharedMemory;
use Laravel\SerializableClosure\SerializableClosure;

// The trace arguments could hold values that cannot be serialized (closures, resources)
ini_set('zend.exception_ignore_args', '1');

require $argv[1];

/** @var array{SerializableClosure, array, SerializableClosure|null, string} $payload */
$payload = unserialize((string)stream_get_contents(STDIN));
[$closure, $args, $onFinish, $prefix] = $payload;

/** @psalm-suppress InternalMethod */
SharedMemory::usePrefix($prefix);

try {
    $result = $closure->getClosure()(...$args);
} catch (Throwable $ex) {
    $result = $ex;
}

// Called after the closure, even when it throws, as ForkHandler does in the child
if ($onFinish !== null) {
    $onFinish->getClosure()();
}

try {
    $output = serialize($result);
} catch (Throwable $ex) {
    // e.g. the closure returned a Closure: the parent re-throws the serialization error
    $output = serialize($ex);
}

file_put_contents('php://fd/3', $output);
