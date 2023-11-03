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
namespace Pop\Queue\Processor;

use Pop\Queue\Queue;

/**
 * Abstract process class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
abstract class AbstractProcessor implements ProcessorInterface
{

    /**
     * Processor jobs
     * @var array
     */
    protected array $jobs = [];

    /**
     * Job results
     * @var array
     */
    protected array $results = [];

    /**
     * Completed jobs
     * @var array
     */
    protected array $completed = [];

    /**
     * Failed jobs
     * @var array
     */
    protected array $failed = [];

    /**
     * Failed jobs exceptions
     * @var array
     */
    protected array $failedExceptions = [];

    /**
     * Add job
     *
     * @param  AbstractJob $job
     * @param  ?int        $maxAttempts
     * @return AbstractProcessor
     */
    public function addJob(AbstractJob $job, ?int $maxAttempts = null): AbstractProcessor
    {
        if ($maxAttempts !== null) {
            $job->setMaxAttempts($maxAttempts);
        }
        if ($this->isFilo()) {
            array_unshift($this->jobs, $job);
        } else {
            $this->jobs[] = $job;
        }
        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @param  ?int  $maxAttempts
     * @return AbstractProcessor
     */
    public function addJobs(array $jobs, ?int $maxAttempts = null): AbstractProcessor
    {
        foreach ($jobs as $job) {
            $this->addJob($job, $maxAttempts);
        }
        return $this;
    }

    /**
     * Add task (alias)
     *
     * @param  Task $task
     * @param  ?int $maxAttempts
     * @return AbstractProcessor
     */
    public function addTask(Task $task, ?int $maxAttempts = 0): AbstractProcessor
    {
        return $this->addJob($task, $maxAttempts);
    }

    /**
     * Add tasks
     *
     * @param  array $tasks
     * @param  ?int  $maxAttempts
     * @return AbstractProcessor
     */
    public function addTasks(array $tasks, ?int $maxAttempts = null): AbstractProcessor
    {
        foreach ($tasks as $task) {
            $this->addTask($task, $maxAttempts);
        }
        return $this;
    }

    /**
     * Get jobs
     *
     * @return array
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Get job
     *
     * @param  int $index
     * @return AbstractJob|null
     */
    public function getJob(int $index): AbstractJob|null
    {
        return $this->jobs[$index] ?? null;
    }

    /**
     * Has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return (count($this->jobs) > 0);
    }

    /**
     * Has job
     *
     * @param  int $index
     * @return bool
     */
    public function hasJob(int $index): bool
    {
        return (isset($this->jobs[$index]));
    }

    /**
     * Has next job
     *
     * @return bool
     */
    public function hasNextJob(): bool
    {
        $current = key($this->jobs);
        return (($current !== null) && ($current < count($this->jobs)));
    }

    /**
     * Get next index
     *
     * @return int
     */
    public function getNextIndex(): int
    {
        $index = key($this->jobs);
        next($this->jobs);
        return $index;
    }

    /**
     * Get job results
     *
     * @return array
     */
    public function getJobResults(): array
    {
        return $this->results;
    }

    /**
     * Get job result
     *
     * @param  mixed $index
     * @return mixed
     */
    public function getJobResult(mixed $index): mixed
    {
        return $this->results[$index] ?? null;
    }

    /**
     * Has job results
     *
     * @return bool
     */
    public function hasJobResults(): bool
    {
        return !empty($this->results);
    }

    /**
     * Get completed jobs
     *
     * @return array
     */
    public function getCompletedJobs(): array
    {
        return $this->completed;
    }

    /**
     * Get completed job
     *
     * @param  mixed $index
     * @return AbstractJob|null
     */
    public function getCompletedJob(mixed $index): AbstractJob|null
    {
        return $this->completed[$index] ?? null;
    }

    /**
     * Has completed jobs
     *
     * @return bool
     */
    public function hasCompletedJobs(): bool
    {
        return !empty($this->completed);
    }

    /**
     * Get failed jobs
     *
     * @return array
     */
    public function getFailedJobs(): array
    {
        return $this->failed;
    }

    /**
     * Get failed job
     *
     * @param  mixed $index
     * @return AbstractJob|null
     */
    public function getFailedJob(mixed $index): AbstractJob|null
    {
        return $this->failed[$index] ?? null;
    }

    /**
     * Has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool
    {
        return !empty($this->failed);
    }

    /**
     * Get failed exceptions
     *
     * @return array
     */
    public function getFailedExceptions(): array
    {
        return $this->failedExceptions;
    }

    /**
     * Get failed exception
     *
     * @param  mixed $index
     * @return \Exception|null
     */
    public function getFailedException($index): \Exception|null
    {
        return $this->failedExceptions[$index] ?? null;
    }

    /**
     * Has failed exceptions
     *
     * @return bool
     */
    public function hasFailedExceptions(): bool
    {
        return !empty($this->failedExceptions);
    }

    /**
     * Process next job
     *
     * @param  ?Queue $queue
     * @return mixed
     */
    abstract public function processNext(?Queue $queue = null): mixed;

}
