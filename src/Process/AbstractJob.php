<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Process;

use Pop\Application;
use Pop\Console\Command\AbstractCommand;
use Pop\Utils\CallableObject;
use Laravel\SerializableClosure\SerializableClosure;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Abstract job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
abstract class AbstractJob implements JobInterface
{

    /**
     * Job ID
     * @var ?string
     */
    protected ?string $id = null;

    /**
     * Job Description
     * @var ?string
     */
    protected ?string $description = null;

    /**
     * Job callable
     * @var ?CallableObject
     */
    protected ?CallableObject $callable = null;

    /**
     * Job application command - an invocation string routed through the
     * application (e.g. 'greet Nick'), or an argv-style array of already
     * split segments (e.g. ['notify', 'Hello there, world']). The string
     * form is split on whitespace by the router, so the array form is the
     * only way to pass a value that itself contains spaces.
     * @var string|array|null
     */
    protected string|array|null $command = null;

    /**
     * Job CLI executable command - a shell command string (runs via the
     * shell, e.g. 'ls -la | wc -l'), or an argv-style array to run with no
     * shell involved at all (e.g. ['ls', '-la'] - the safer form when any
     * part of the command isn't a fully-trusted literal, since shell
     * metacharacters in an argv element are inert)
     * @var string|array|null
     */
    protected string|array|null $exec = null;

    /**
     * Job started timestamp
     * @var ?int
     */
    protected ?int $started = null;

    /**
     * Job completed timestamp
     * @var ?int
     */
    protected ?int $completed = null;

    /**
     * Job failed timestamp
     * @var ?int
     */
    protected ?int $failed = null;

    /**
     * Job failed messages
     * @var array
     */
    protected array $failedMessages = [];

    /**
     * Max attempts
     * @var int
     */
    protected int $maxAttempts = 0;

    /**
     * Attempts
     * @var int
     */
    protected int $attempts = 0;

    /**
     * Run until property
     * @var int|string|null
     */
    protected int|string|null $runUntil = null;

    /**
     * Serialize closure
     * @var ?string
     */
    protected ?string $serializedClosure = null;

    /**
     * Serialize parameters
     * @var ?array
     */
    protected ?array $serializedParameters = null;

    /**
     * Job results
     * @var mixed
     */
    protected mixed $results = null;

    /**
     * Timestamp before which this job is not eligible for reservation
     * @var ?int
     */
    protected ?int $availableAt = null;

    /**
     * Soft execution timeout, in seconds (only enforced when ext-pcntl is loaded)
     * @var ?int
     */
    protected ?int $timeout = null;

    /**
     * Retry backoff: a fixed delay in seconds, or a per-attempt schedule that
     * holds at its last value for further attempts. Null = immediate retry.
     * @var int|array|null
     */
    protected int|array|null $backoff = null;

    /**
     * Constructor
     *
     * Instantiate the job object
     *
     * @param  mixed   $callable
     * @param  mixed   $params
     * @param  ?string $id
     */
    public function __construct(mixed $callable = null, mixed $params = null, ?string $id = null)
    {
        if ($callable !== null) {
            $this->setCallable($callable, $params);
        }
        if ($id !== null) {
            $this->setJobId($id);
        }
    }

    /**
     * Generate job ID
     *
     * @return string
     */
    public function generateJobId(): string
    {
        $this->id = sha1(uniqid(rand()) . time());
        return $this->id;
    }

    /**
     * Set job ID
     *
     * @param  string $id
     * @return AbstractJob
     */
    public function setJobId(string $id): AbstractJob
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get job ID
     *
     * @return ?string
     */
    public function getJobId(): ?string
    {
        if (!$this->hasJobId()) {
            $this->generateJobId();
        }
        return $this->id;
    }

    /**
     * Has job ID
     *
     * @return bool
     */
    public function hasJobId(): bool
    {
        return ($this->id !== null);
    }

    /**
     * Set job description
     *
     * @param  string $description
     * @return AbstractJob
     */
    public function setJobDescription(string $description): AbstractJob
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get job description
     *
     * @return ?string
     */
    public function getJobDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Has job description
     *
     * @return bool
     */
    public function hasJobDescription(): bool
    {
        return ($this->description !== null);
    }

    /**
     * Get job results
     *
     * @return mixed
     */
    public function getResults(): mixed
    {
        return $this->results;
    }

    /**
     * Has job results
     *
     * @return bool
     */
    public function hasResults(): bool
    {
        return !empty($this->results);
    }

    /**
     * Set job callable
     *
     * @param  mixed $callable
     * @param  mixed $params
     * @return AbstractJob
     */
    public function setCallable(mixed $callable, mixed $params = null): AbstractJob
    {

        if (!($callable instanceof CallableObject)) {
            $this->callable = new CallableObject($callable, $params);
        } else {
            $this->callable = $callable;
            if ($params !== null) {
                if (is_array($params)) {
                    $this->callable->addParameters($params);
                } else {
                    $this->callable->addParameter($params);
                }
            }
        }

        return $this;
    }

    /**
     * Set job application command
     *
     * @param  string|array $command
     * @return AbstractJob
     */
    public function setCommand(string|array $command): AbstractJob
    {
        $this->command = $command;
        return $this;
    }

    /**
     * Set job CLI executable command
     *
     * @param  string|array $command
     * @return AbstractJob
     */
    public function setExec(string|array $command): AbstractJob
    {
        $this->exec = $command;
        return $this;
    }

    /**
     * Get job callable
     *
     * @return ?CallableObject
     */
    public function getCallable(): ?CallableObject
    {
        return $this->callable;
    }

    /**
     * Get job application command
     *
     * @return string|array|null
     */
    public function getCommand(): string|array|null
    {
        return $this->command;
    }

    /**
     * Get job CLI executable command
     *
     * @return string|array|null
     */
    public function getExec(): string|array|null
    {
        return $this->exec;
    }

    /**
     * Has job callable
     *
     * @return bool
     */
    public function hasCallable(): bool
    {
        return ($this->callable !== null);
    }

    /**
     * Has job application command
     *
     * @return bool
     */
    public function hasCommand(): bool
    {
        return ($this->command !== null);
    }

    /**
     * Has job CLI executable command
     *
     * @return bool
     */
    public function hasExec(): bool
    {
        return ($this->exec !== null);
    }

    /**
     * Set max attempts
     *
     * @param  int $maxAttempts
     * @return AbstractJob
     */
    public function setMaxAttempts(int $maxAttempts): AbstractJob
    {
        $this->maxAttempts = $maxAttempts;
        return $this;
    }

    /**
     * Get max attempts
     *
     * @return int
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Has max attempts
     *
     * @return bool
     */
    public function hasMaxAttempts(): bool
    {
        return ($this->maxAttempts > 0);
    }

    /**
     * Is job set for only one max attempt
     *
     * @return bool
     */
    public function isAttemptOnce(): bool
    {
        return ($this->maxAttempts == 1);
    }

    /**
     * Get actual attempts
     *
     * @return int
     */
    public function getAttempts(): int
    {
        return $this->attempts;
    }

    /**
     * Has actual attempts
     *
     * @return bool
     */
    public function hasAttempts(): bool
    {
        return ($this->attempts > 0);
    }

    /**
     * Set the run until property
     *
     * @param  int|string $runUntil
     * @return AbstractJob
     */
    public function runUntil(int|string $runUntil): AbstractJob
    {
        $this->runUntil = $runUntil;
        return $this;
    }

    /**
     * Has run until
     *
     * @return bool
     */
    public function hasRunUntil(): bool
    {
        return ($this->runUntil !== null);
    }

    /**
     * Get run until value
     *
     * @return int|string|null
     */
    public function getRunUntil(): int|string|null
    {
        return $this->runUntil;
    }

    /**
     * Delay job availability
     *
     * @param  int|string $when  Seconds from now (int below 1000000000), an absolute
     *                           timestamp (int), or a strtotime()-parseable string
     * @throws Exception
     * @return AbstractJob
     */
    public function delay(int|string $when): AbstractJob
    {
        if (is_int($when)) {
            $this->availableAt = ($when < 1000000000) ? (time() + $when) : $when;
        } else {
            $timestamp = strtotime($when);
            if ($timestamp === false) {
                throw new Exception('Error: That delay value is not valid.');
            }
            $this->availableAt = $timestamp;
        }

        return $this;
    }

    /**
     * Get available-at timestamp
     *
     * @return ?int
     */
    public function getAvailableAt(): ?int
    {
        return $this->availableAt;
    }

    /**
     * Determine if the job is currently available (no delay, or delay has elapsed)
     *
     * @return bool
     */
    public function isAvailable(): bool
    {
        return ($this->availableAt === null) || (time() >= $this->availableAt);
    }

    /**
     * Set soft execution timeout
     *
     * @param  int $seconds
     * @return AbstractJob
     */
    public function setTimeout(int $seconds): AbstractJob
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Get soft execution timeout
     *
     * @return ?int
     */
    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * Has soft execution timeout
     *
     * @return bool
     */
    public function hasTimeout(): bool
    {
        return ($this->timeout !== null);
    }

    /**
     * Set retry backoff (fixed seconds, or a per-attempt schedule)
     *
     * @param  int|array $backoff
     * @return AbstractJob
     */
    public function setBackoff(int|array $backoff): AbstractJob
    {
        $this->backoff = $backoff;
        return $this;
    }

    /**
     * Get retry backoff
     *
     * @return int|array|null
     */
    public function getBackoff(): int|array|null
    {
        return $this->backoff;
    }

    /**
     * Has retry backoff
     *
     * @return bool
     */
    public function hasBackoff(): bool
    {
        return ($this->backoff !== null);
    }

    /**
     * Get the backoff delay, in seconds, for the current attempt count
     *
     * @return int
     */
    public function getBackoffDelay(): int
    {
        if (empty($this->backoff)) {
            return 0;
        }
        if (is_int($this->backoff)) {
            return $this->backoff;
        }

        $index = max(min($this->attempts, count($this->backoff)) - 1, 0);
        return (int)$this->backoff[$index];
    }

    /**
     * Determine if the job has expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!empty($this->runUntil)) {
            $runUntil = null;
            if (is_string($this->runUntil) && (strtotime($this->runUntil) !== false)) {
                $runUntil = strtotime($this->runUntil);
            } else if (is_numeric($this->runUntil) && ((string)(int)$this->runUntil == $this->runUntil)) {
                $runUntil = $this->runUntil;
            }

            if ($runUntil !== null) {
                return (time() > $runUntil);
            }
        }

        return false;
    }

    /**
     * Determine if the job has exceeded max attempts
     *
     * @return bool
     */
    public function hasExceededMaxAttempts(): bool
    {
        if ($this->hasMaxAttempts()) {
            return ($this->attempts >= $this->maxAttempts);
        }

        return false;
    }

    /**
     * Determine if the job is still valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return ((!$this->isExpired()) && (!$this->hasExceededMaxAttempts()));
    }

    /**
     * Has job run yet
     *
     * @return bool
     */
    public function hasNotRun(): bool
    {
        return (($this->started === null) && ($this->completed === null));
    }

    /**
     * Start job
     *
     * @return AbstractJob
     */
    public function start(): AbstractJob
    {
        $this->started = time();
        return $this;
    }

    /**
     * Get started timestamp
     *
     * @return ?int
     */
    public function getStarted(): ?int
    {
        return $this->started;
    }

    /**
     * Has job started
     *
     * @return bool
     */
    public function hasStarted(): bool
    {
        return ($this->started !== null);
    }

    /**
     * Is job running and has not completed or failed yet
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return (($this->started !== null) && ($this->completed === null) && ($this->failed === null));
    }

    /**
     * Complete job
     *
     * @return AbstractJob
     */
    public function complete(): AbstractJob
    {
        $this->completed = time();
        $this->attempts++;
        return $this;
    }

    /**
     * Get completed timestamp
     *
     * @return ?int
     */
    public function getCompleted(): ?int
    {
        return $this->completed;
    }

    /**
     * Get how long the job took to run, in seconds, or null unless it both
     * started and completed. Convenience for observability listeners, which
     * would otherwise all repeat the same timestamp subtraction.
     *
     * @return ?int
     */
    public function getDuration(): ?int
    {
        return (($this->started !== null) && ($this->completed !== null))
            ? ($this->completed - $this->started) : null;
    }

    /**
     * Is job complete
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return ($this->completed !== null);
    }

    /**
     * Set job as failed
     *
     * @param  ?string $message
     * @return AbstractJob
     */
    public function failed(?string $message = null): AbstractJob
    {
        $this->failed = time();
        $this->attempts++;

        if ($message !== null) {
            $this->addFailedMessage($message);
        }

        return $this;
    }

    /**
     * Has job failed
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return ($this->failed !== null);
    }

    /**
     * Get failed timestamp
     *
     * @return ?int
     */
    public function getFailed(): ?int
    {
        return $this->failed;
    }

    /**
     * Add failed message
     *
     * @param  string $message
     * @return AbstractJob
     */
    public function addFailedMessage(string $message): AbstractJob
    {
        $index = $this->failed ?? time();
        $this->failedMessages[$index] = $message;
        return $this;
    }

    /**
     * Has failed messages
     *
     * @return bool
     */
    public function hasFailedMessages(): bool
    {
        return !empty($this->failedMessages);
    }

    /**
     * Get failed messages
     *
     * @return array
     */
    public function getFailedMessages(): array
    {
        return $this->failedMessages;
    }

    /**
     * Run job
     *
     * @param  ?Application $application
     * @return mixed
     */
    public function run(?Application $application = null): mixed
    {
        $this->start();

        if ($this->hasCallable()) {
            return $this->loadCallable($application);
        }
        if (($this->hasCommand()) && ($application !== null)) {
            return $this->runCommand($application);
        }
        if ($this->hasExec()) {
            return $this->runExec();
        }

        return null;
    }

    /**
     * Load callable
     *
     * @param  ?Application $application
     * @throws Exception|\Pop\Utils\Exception|\ReflectionException
     * @return mixed
     */
    protected function loadCallable(?Application $application = null): mixed
    {
        if ($this->callable === null) {
            throw new Exception('Error: The callable for this job was not set.');
        }

        if ($application !== null) {
            if ($this->callable->hasParameters()) {
                $parameters = $this->callable->getParameters();
                array_unshift($parameters, $application);
                $this->callable->setParameters($parameters);
            } else {
                $this->callable->addNamedParameter('application', $application);
            }
        }

        $this->results = $this->callable->call();
        return $this->results;
    }

    /**
     * Run application command
     *
     * @param  Application $application
     * @return mixed
     */
    protected function runCommand(Application $application): mixed
    {
        $router = $application->router();
        $level  = ob_get_level();
        $output = '';

        ob_start();

        try {
            // run(false, ...) - never let an unresolved route call exit()
            // and take the whole worker process down with it.
            $application->run(false, $this->command);
        } finally {
            // Unwind to the level we started at, rather than closing exactly
            // one buffer: the command may have thrown, or opened a buffer of
            // its own and not closed it. Either way a leaked buffer is never
            // reclaimed in a long-running worker - it grows without bound and
            // silently swallows everything printed afterwards. Inner buffers
            // hold the later output, so each unwind prepends to what we have.
            while (ob_get_level() > $level) {
                $output = ob_get_clean() . $output;
            }
        }

        // Whether the command resolved is the router's call, not a lookup
        // against route definition keys - matching by definition string
        // meant a real invocation ('greet Nick') could never be run, only
        // the literal route definition ('greet <name>') could.
        if (($router === null) || !$router->hasDispatchable()) {
            return false;
        }

        $this->describeFromCommand($router->getDispatchable());

        $this->results = array_values(array_filter(explode(PHP_EOL, $output), fn($line) => $line !== ''));
        return $this->results;
    }

    /**
     * Borrow a job description from the dispatched command, if it has one
     * and the job was not given one explicitly. A command already documents
     * itself, so a queued command job need not be anonymous in the registry.
     *
     * Guarded by instanceof rather than a hard dependency: pop-console
     * arrives transitively through popphp, and instanceof against a missing
     * class is simply false rather than an error.
     *
     * @param  mixed $dispatchable
     * @return void
     */
    protected function describeFromCommand(mixed $dispatchable): void
    {
        if ($this->hasJobDescription() || !($dispatchable instanceof AbstractCommand)) {
            return;
        }

        $description = $dispatchable->getHelp() ?: $dispatchable->getName();

        if (!empty($description)) {
            $this->setJobDescription($description);
        }
    }

    /**
     * Build the Process instance for this job's exec command, without
     * running it. Split out from runExec() purely so a test can construct
     * one and inspect its configured timeout directly, without actually
     * executing a command.
     *
     * @return Process
     */
    protected function buildExecProcess(): Process
    {
        $process = is_array($this->exec)
            ? new Process($this->exec)
            : Process::fromShellCommandline($this->exec);

        // Process defaults to a 60-second timeout on every instance unless
        // explicitly told otherwise - disable it entirely when this job has
        // no configured timeout, matching exec()'s old no-timeout-by-default
        // behavior. Getting this wrong would silently cap every exec job at
        // 60 seconds regardless of what the job actually needed.
        $process->setTimeout($this->hasTimeout() ? $this->getTimeout() : null);

        return $process;
    }

    /**
     * Run CLI executable command
     *
     * @throws ProcessFailedException|ProcessTimedOutException
     * @return mixed
     */
    protected function runExec(): mixed
    {
        $process = $this->buildExecProcess();
        $process->mustRun();

        $this->results = array_values(array_filter(explode(PHP_EOL, $process->getOutput()), fn($line) => $line !== ''));
        return $this->results;
    }

    /**
     * Sleep magic method
     *
     * @return array
     */
    public function __sleep(): array
    {
        if (!empty($this->callable) && ($this->callable->getCallable() instanceof \Closure)) {
            $serializedClosure       = new SerializableClosure($this->callable->getCallable());
            $this->serializedClosure = serialize($serializedClosure);
            if ($this->callable->hasParameters()) {
                $this->serializedParameters = $this->callable->getParameters();
            }
            $this->callable = null;
        }

        return array_keys(get_object_vars($this));
    }

    /**
     * Wakeup magic method
     *
     * @return void
     */
    public function __wakeup(): void
    {
        if (!empty($this->serializedClosure)) {
            $serializedClosure          = unserialize($this->serializedClosure);
            $callable                   = $serializedClosure->getClosure();
            $this->callable             = new CallableObject($callable, $this->serializedParameters);
            $this->serializedClosure    = null;
            $this->serializedParameters = null;
        }
    }

}
