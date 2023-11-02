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
namespace Pop\Queue\Processor\Jobs;

use Pop\Application;
use Pop\Utils\CallableObject;
use Opis\Closure\SerializableClosure;

/**
 * Abstract job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
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
     * Job running flag
     * @var bool
     */
    protected bool $running = false;

    /**
     * Job completed flag
     * @var bool
     */
    protected bool $completed = false;

    /**
     * Job completed timestamp
     * @var ?int
     */
    protected ?int $completedTimestamp = null;

    /**
     * Job failed flag
     * @var bool
     */
    protected bool $failed = false;

    /**
     * Job failed timestamp
     * @var ?int
     */
    protected ?int $failedTimestamp = null;

    /**
     * Attempt once flag
     * @var bool
     */
    protected bool $attemptOnce = false;

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
     * Constructor
     *
     * Instantiate the job object
     *
     * @param  mixed  $callable
     * @param  mixed  $params
     * @param  string $id
     * @param  string $description
     */
    public function __construct(mixed $callable = null, mixed $params = null, ?string $id = null, ?string $description = null)
    {
        if ($callable !== null) {
            $this->setCallable($callable, $params);
        }
        if ($id !== null) {
            $this->setJobId($id);
        }
        if ($description !== null) {
            $this->setJobDescription($description);
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
     * @return CallableObject
     */
    public function getCallable(): mixed
    {
        return $this->callable;
    }

    /**
     * Get job application command
     *
     * @return s?tring
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
     * Set job to only attempt once
     *
     * @param  bool $attemptOnce
     * @return AbstractJob
     */
    public function attemptOnce(bool $attemptOnce = true): AbstractJob
    {
        $this->attemptOnce = (bool)$attemptOnce;
        return $this;
    }

    /**
     * Set job to only attempt to run once
     *
     * @return bool
     */
    public function isAttemptOnce(): bool
    {
        return $this->attemptOnce;
    }

    /**
     * Set job as running
     *
     * @return AbstractJob
     */
    public function setAsRunning(): AbstractJob
    {
        $this->running = true;
        return $this;
    }

    /**
     * Is job running
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Set job as completed
     *
     * @return AbstractJob
     */
    public function setAsCompleted(): AbstractJob
    {
        $this->completed          = true;
        $this->completedTimestamp = time();
        return $this;
    }

    /**
     * Is job complete
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->completed;
    }

    /**
     * Get completed timestamp
     *
     * @return ?int
     */
    public function getCompletedTimestamp(): ?int
    {
        return $this->completedTimestamp;
    }

    /**
     * Set job as failed
     *
     * @return AbstractJob
     */
    public function setAsFailed(): AbstractJob
    {
        $this->failed          = true;
        $this->failedTimestamp = time();
        return $this;
    }

    /**
     * Has job failed
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->failed;
    }

    /**
     * Get failed timestamp
     *
     * @return ?int
     */
    public function getFailedTimestamp(): ?int
    {
        return $this->failedTimestamp;
    }

    /**
     * Run job
     *
     * @param  ?Application $application
     * @return mixed
     */
    public function run(?Application $application = null): mixed
    {
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

        return $this->callable->call();
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
            $output = null;

            ob_start();
            $application->run(true, $this->command);
            $output = ob_get_clean();

            return array_filter(explode(PHP_EOL, $output));
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
        return $output;
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