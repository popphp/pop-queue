<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue;

use Pop\Application;
use Pop\Queue\Adapter\AdapterInterface;
use Pop\Queue\Adapter\TaskAdapterInterface;
use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;
use Pop\Queue\Process\TimeoutException;

/**
 * Queue class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.3
 */
class Queue extends AbstractQueue
{

    /**
     * Queue priority constants
     */
    const FIFO = 'FIFO'; // Same as LILO
    const FILO = 'FILO'; // Same as LIFO

    /**
     * Constructor
     *
     * Instantiate the queue object
     *
     * @param string $name
     * @param AdapterInterface|TaskAdapterInterface $adapter
     * @param ?string $priority
     */
    public function __construct(string $name, AdapterInterface|TaskAdapterInterface $adapter, ?string $priority = null)
    {
        $this->setName($name);
        $this->setAdapter($adapter);
        if ($priority !== null) {
            $this->setPriority($priority);
        }
    }

    /**
     * Create the queue object
     *
     * @param  string $name
     * @param  AdapterInterface|TaskAdapterInterface $adapter
     * @param  ?string $priority
     * @return Queue
     */
    public static function create(
        string $name, AdapterInterface|TaskAdapterInterface $adapter, ?string $priority = null
    ): Queue
    {
        return new self($name, $adapter, $priority);
    }

    /**
     * Set queue priority
     *
     * @param  string $priority
     * @return Queue
     */
    public function setPriority(string $priority = 'FIFO'): Queue
    {
        $this->adapter->setPriority($priority);
        return $this;
    }

    /**
     * Get queue priority
     *
     * @return string
     */
    public function getPriority(): string
    {
        return $this->adapter->getPriority();
    }

    /**
     * Is FIFO
     *
     * @return bool
     */
    public function isFifo(): bool
    {
        return $this->adapter->isFifo();
    }

    /**
     * Is FILO
     *
     * @return bool
     */
    public function isFilo(): bool
    {
        return $this->adapter->isFilo();
    }

    /**
     * Is LILO (alias to FIFO)
     *
     * @return bool
     */
    public function isLilo(): bool
    {
        return $this->adapter->isLilo();
    }

    /**
     * Is LIFO (alias to FILO)
     *
     * @return bool
     */
    public function isLifo(): bool
    {
        return $this->adapter->isLifo();
    }

    /**
     * Add job
     *
     * @param  AbstractJob $job
     * @param  ?int        $maxAttempts
     * @return Queue
     */
    public function addJob(AbstractJob $job, ?int $maxAttempts = null): Queue
    {
        if ($maxAttempts !== null) {
            $job->setMaxAttempts($maxAttempts);
        }
        $this->adapter->push($job);

        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @param  ?int  $maxAttempts
     * @return Queue
     */
    public function addJobs(array $jobs, ?int $maxAttempts = null): Queue
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
     * @throws Exception
     * @return Queue
     */
    public function addTask(Task $task, ?int $maxAttempts = null): Queue
    {
        if (!($this->adapter instanceof TaskAdapterInterface)) {
            throw new Exception('Error: That queue adapter does not support scheduled tasks');
        }
        if ($maxAttempts !== null) {
            $task->setMaxAttempts($maxAttempts);
        }

        $this->adapter->schedule($task);

        return $this;
    }

    /**
     * Add tasks
     *
     * @param  array $tasks
     * @param  ?int  $maxAttempts
     * @throws Exception
     * @return Queue
     */
    public function addTasks(array $tasks, ?int $maxAttempts = null): Queue
    {
        foreach ($tasks as $task) {
            $this->addTask($task, $maxAttempts);
        }
        return $this;
    }

    /**
     * Work next job
     *
     * @param  ?Application $application
     * @return ?AbstractJob
     */
    public function work(?Application $application = null): ?AbstractJob
    {
        $job = $this->adapter->reserve();
        if ($job === null) {
            return null;
        }

        if (!$job->isValid()) {
            $this->adapter->bury($job, 'Exceeded max attempts or expired before execution');
            return $job;
        }

        try {
            $this->runWithTimeout($job, $application);
            $job->complete();
            $this->adapter->delete($job);
        } catch (\Throwable $e) {
            $job->failed($e->getMessage());
            if ($job->isValid()) {
                $this->adapter->release($job);
            } else {
                $this->adapter->bury($job, $e->getMessage());
            }
        }

        return $job;
    }

    /**
     * Run a job, enforcing its soft timeout when ext-pcntl is available
     *
     * @param  AbstractJob  $job
     * @param  ?Application $application
     * @return mixed
     */
    protected function runWithTimeout(AbstractJob $job, ?Application $application): mixed
    {
        if (!$job->hasTimeout() || !extension_loaded('pcntl')) {
            return $job->run($application);
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function() use ($job) {
            throw new TimeoutException(
                'Error: Job ' . $job->getJobId() . ' exceeded its ' . $job->getTimeout() . ' second timeout.'
            );
        });
        pcntl_alarm($job->getTimeout());

        try {
            return $job->run($application);
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_DFL);
        }
    }

    /**
     * Evaluate every given task exactly once against the current time and
     * run the ones that are due, returning the ones that ran (successfully
     * or not) keyed by job ID. Coarse (non-sub-minute) tasks are always
     * considered; pass $onlySubMinute = true to skip them (used by run()'s
     * per-second tick loop, where a coarse task's single evaluation already
     * happened on the shared first pass and doesn't need repeating).
     *
     * @param  array        $tasks         taskId => Task
     * @param  ?Application $application
     * @param  bool         $onlySubMinute
     * @return array  jobId => Task, for every task that ran this pass
     */
    protected function evaluateTasksOnce(array $tasks, ?Application $application, bool $onlySubMinute = false): array
    {
        if (!($this->adapter instanceof TaskAdapterInterface)) {
            return [];
        }

        $ran = [];

        foreach ($tasks as $taskId => $task) {
            $isSubMinute = $task->cron()->hasSeconds();

            if ($onlySubMinute && !$isSubMinute) {
                continue;
            }

            if ($isSubMinute) {
                $task->__wakeup();
            }

            if ((!$task->isValid()) || (!$task->cron()->evaluate())) {
                continue;
            }

            try {
                $task->run($application);
                $task->complete();
                if (!$isSubMinute) {
                    $this->adapter->updateTask($task);
                }
                $ran[$task->getJobId()] = $task;
            } catch (\Exception $e) {
                $task->failed($e->getMessage());
                if ($isSubMinute) {
                    $this->adapter->removeTask($taskId);
                    $this->adapter->schedule($task);
                } else {
                    $this->adapter->updateTask($task);
                }
                $ran[$task->getJobId()] = $task;
            }
        }

        return $ran;
    }

    /**
     * Run schedule
     *
     * Evaluates every scheduled task fairly: all tasks get one shared
     * evaluation pass immediately, then - only if at least one sub-minute
     * task exists - up to 59 more passes (one per second) considering only
     * the sub-minute tasks. This ensures no single task's per-tick work
     * blocks any other task's evaluation on the same tick.
     *
     * @param  ?Application $application
     * @throws Process\Exception
     * @return array
     */
    public function run(?Application $application = null): array
    {
        $tasks = [];

        if ((!($this->adapter instanceof TaskAdapterInterface)) || (!$this->adapter->hasTasks())) {
            return $tasks;
        }

        $scheduledTasks = [];
        foreach ($this->adapter->getTasks() as $taskId) {
            $task = $this->adapter->getTask($taskId);
            if ($task instanceof Task) {
                $scheduledTasks[$taskId] = $task;
            }
        }

        foreach ($this->evaluateTasksOnce($scheduledTasks, $application) as $jobId => $task) {
            $tasks[$jobId] = $task;
        }

        $hasSubMinute = false;
        foreach ($scheduledTasks as $task) {
            if ($task->cron()->hasSeconds()) {
                $hasSubMinute = true;
                break;
            }
        }

        if ($hasSubMinute) {
            for ($tick = 1; $tick < 60; $tick++) {
                sleep(1);
                foreach ($this->evaluateTasksOnce($scheduledTasks, $application, true) as $jobId => $task) {
                    $tasks[$jobId] = $task;
                }
            }
        }

        return $tasks;
    }

    /**
     * Clear jobs from queue
     *
     * @return Queue
     */
    public function clear(): Queue
    {
        $this->adapter->clear();
        return $this;
    }

    /**
     * Clear dead-letter jobs from queue
     *
     * @return Queue
     */
    public function clearFailed(): Queue
    {
        $this->adapter->clearDead();
        return $this;
    }

    /**
     * Clear tasks from queue
     *
     * @return Queue
     */
    public function clearTasks(): Queue
    {
        if ($this->adapter instanceof TaskAdapterInterface) {
            $this->adapter->clearTasks();
        }
        return $this;
    }

}
