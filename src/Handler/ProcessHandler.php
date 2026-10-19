<?php

namespace ByJG\PHPThread\Handler;

use ByJG\PHPThread\SharedMemory;
use ByJG\PHPThread\ThreadStatus;
use Closure;
use InvalidArgumentException;
use Composer\Autoload\ClassLoader;
use Laravel\SerializableClosure\SerializableClosure;
use ReflectionClass;
use RuntimeException;
use Throwable;

/**
 * Runs the closure in a child PHP CLI process started with proc_open.
 *
 * It works outside the CLI too (PHP-FPM, FrankenPHP, ...). The closure, its arguments and the
 * onFinish closure are serialized with laravel/serializable-closure and sent to the child,
 * so they cannot hold resources and the objects they capture must be serializable.
 */
class ProcessHandler implements ThreadInterface
{
    protected static ?string $phpBinary = null;

    private Closure $closure;
    private ?Closure $onFinish;

    /** @var resource|null */
    private $process = null;
    /** @var resource|null The pipe where the child writes the serialized result */
    private $pipe = null;
    private string $output = '';

    private bool $started = false;
    private bool $terminated = false;
    private mixed $threadResult = null;

    public function __construct(?Closure $onFinish = null)
    {
        if (!self::isAvailable()) {
            throw new RuntimeException(
                'ProcessHandler needs proc_open and a PHP CLI binary. Use ProcessHandler::setPhpBinary() to set it.'
            );
        }

        $this->onFinish = $onFinish;
    }

    /**
     * Set the PHP CLI binary used to run the closure, or restore the automatic choice with null.
     *
     * @throws InvalidArgumentException When the file is not executable
     */
    public static function setPhpBinary(?string $phpBinary): void
    {
        if ($phpBinary !== null && !(is_file($phpBinary) && is_executable($phpBinary))) {
            throw new InvalidArgumentException("'$phpBinary' is not an executable file");
        }
        self::$phpBinary = $phpBinary;
    }

    /**
     * The PHP CLI binary: the one set by setPhpBinary(), PHP_BINARY in the CLI,
     * otherwise PHP_BINDIR/php or php in the PATH. Null if none is found.
     */
    public static function getPhpBinary(): ?string
    {
        if (self::$phpBinary !== null) {
            return self::$phpBinary;
        }

        // Outside the CLI PHP_BINARY is the server binary (e.g. php-fpm), not the CLI
        if (PHP_SAPI === 'cli') {
            return PHP_BINARY;
        }

        $candidates = [PHP_BINDIR . '/php'];
        foreach (explode(PATH_SEPARATOR, (string)getenv('PATH')) as $dir) {
            if ($dir !== '') {
                $candidates[] = $dir . '/php';
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * proc_open can be used (a function in disable_functions does not exist since PHP 8)
     * and a PHP CLI binary was found
     */
    public static function isAvailable(): bool
    {
        return function_exists('proc_open') && self::getPhpBinary() !== null;
    }

    #[\Override]
    public function setClosure(Closure $closure): void
    {
        $this->closure = $closure;
    }

    /**
     * Start the child process and send it the closure and the arguments
     */
    #[\Override]
    public function start(mixed ...$args): void
    {
        $payload = serialize([
            new SerializableClosure($this->closure),
            $args,
            $this->onFinish === null ? null : new SerializableClosure($this->onFinish),
            SharedMemory::getPrefix(),
        ]);

        // The child inherits STDOUT and STDERR, so what the closure prints goes where a fork would print it
        $process = proc_open(
            [(string)self::getPhpBinary(), __DIR__ . '/process-worker.php', self::autoloadPath()],
            [0 => ['pipe', 'r'], 3 => ['pipe', 'w']],
            $pipes
        );
        if ($process === false) {
            throw new RuntimeException('Couldn\'t start the PHP process');
        }

        $this->process = $process;
        $this->pipe = $pipes[3];
        $this->output = '';
        $this->started = true;
        $this->terminated = false;
        $this->threadResult = null;

        // The child reads the whole payload before writing anything, so this cannot deadlock
        fwrite($pipes[0], $payload);
        fclose($pipes[0]);

        // The result is read while the child runs (isRunning, join), so a large result never blocks it
        stream_set_blocking($this->pipe, false);
    }

    /**
     * The Composer autoloader of the project, required by the child process
     */
    protected static function autoloadPath(): string
    {
        // vendor/composer/ClassLoader.php -> vendor/autoload.php
        return dirname((string)(new ReflectionClass(ClassLoader::class))->getFileName(), 2) . '/autoload.php';
    }

    /**
     * Read what the child wrote so far and close the process when it is done
     */
    private function readOutput(): void
    {
        $pipe = $this->pipe;
        $process = $this->process;
        if ($pipe === null || $process === null) {
            return;
        }

        $this->output .= (string)stream_get_contents($pipe);
        if (!feof($pipe)) {
            return;
        }

        $this->pipe = null;
        $this->process = null;
        fclose($pipe);
        $exitCode = proc_close($process);

        if ($this->output !== '') {
            $this->threadResult = unserialize($this->output);
        } elseif (!$this->terminated) {
            $this->threadResult = new RuntimeException("The PHP process ended without a result (exit code $exitCode)");
        }
        $this->output = '';
    }

    /**
     * Wait for the process to finish and return the closure result, or re-throw the exception it threw.
     *
     * @return mixed
     * @throws Throwable
     */
    #[\Override]
    public function getResult(): mixed
    {
        if (!$this->started) {
            return null;
        }

        $this->join();

        if ($this->threadResult instanceof Throwable) {
            throw $this->threadResult;
        }

        return $this->threadResult;
    }

    /**
     * Send a signal to the child process. A terminated process has no result (null).
     *
     * @param int $signal
     * @param bool $wait
     */
    #[\Override]
    public function terminate(int $signal = SIGKILL, bool $wait = false): void
    {
        if ($this->isRunning() && $this->process !== null) {
            $this->terminated = true;
            proc_terminate($this->process, $signal);

            if ($wait) {
                $this->join();
            }
        }
    }

    #[\Override]
    public function isRunning(): bool
    {
        $this->readOutput();
        return $this->pipe !== null;
    }

    #[\Override]
    public function join(): void
    {
        while ($this->pipe !== null) {
            $read = [$this->pipe];
            $write = null;
            $except = null;
            stream_select($read, $write, $except, 1);
            $this->readOutput();
        }
    }

    #[\Override]
    public function getClassName(): string
    {
        return ProcessHandler::class;
    }

    #[\Override]
    public function getStatus(): ThreadStatus
    {
        if (!$this->started) {
            return ThreadStatus::NotStarted;
        }

        return $this->isRunning() ? ThreadStatus::Running : ThreadStatus::Finished;
    }

    /**
     * A closure that captures this handler (e.g. a Promise) is sent to the child process with a copy of it.
     * As in a forked child, the copy does not own the process: it is not running.
     */
    public function __serialize(): array
    {
        return ['started' => $this->started];
    }

    public function __unserialize(array $data): void
    {
        $this->started = (bool)$data['started'];
        $this->onFinish = null;
    }
}
