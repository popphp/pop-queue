<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Process;

use Pop\Application;
use Pop\Utils\CallableObject;
use Laravel\SerializableClosure\SerializableClosure;

/**
 * Abstract job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.1.0
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
     * Job application command
     * @var ?string
     */
    protected ?string $command = null;

    /**
     * Job CLI executable command
     * @var ?string
     */
    protected ?string $exec = null;

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
     * @param  string $command
     * @return AbstractJob
     */
    public function setCommand(string $command): AbstractJob
    {
        $this->command = $command;
        return $this;
    }

    /**
     * Set job CLI executable command
     *
     * @param  string $command
     * @return AbstractJob
     */
    public function setExec(string $command): AbstractJob
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
     * @return ?string
     */
    public function getCommand(): ?string
    {
        return $this->command;
    }

    /**
     * Get job CLI executable command
     *
     * @return ?string
     */
    public function getExec(): ?string
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
        if (array_key_exists($this->command, $application->router()->getRouteMatch()->getRoutes())) {
            ob_start();
            $application->run(true, $this->command);
            $output = ob_get_clean();

            $this->results = array_filter(explode(PHP_EOL, $output));
            return $this->results;
        }

        return false;
    }

    /**
     * Run CLI executable command
     *
     * @return mixed
     */
    protected function runExec(): mixed
    {
        $output = [];
        exec($this->exec, $output);
        $this->results = $output;
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
