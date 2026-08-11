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
use Pop\Event\Manager as EventManager;
use Pop\Queue\Adapter\AdapterInterface;
use Pop\Queue\Adapter\Memory;
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
     * Event manager, for lifecycle observability hooks (queue.job.*, queue.task.*).
     * If not set, and an Application with its own event manager is passed into
     * work()/run(), that Application's event manager is used instead - see
     * triggerEvent(). If neither is available, event firing is a silent no-op.
     * @var ?EventManager
     */
    protected ?EventManager $events = null;

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
     * Create a Memory-backed queue for testing - a fake, in the sense
     * familiar from other PHP frameworks' testing conventions. Note
     * Memory's own constructor takes $leaseSeconds before $priority
     * (Memory predates this method and that argument order is documented,
     * pre-existing behavior elsewhere in this codebase) - fake()'s own
     * parameter order matches create()'s $priority-before-lease convention
     * instead, and translates between the two internally, so a caller of
     * fake() never needs to know Memory's own argument order.
     *
     * @param  string  $name
     * @param  ?string $priority
     * @param  int     $leaseSeconds
     * @return Queue
     */
    public static function fake(string $name = 'pop-queue', ?string $priority = null, int $leaseSeconds = 60): Queue
    {
        return new self($name, new Memory($leaseSeconds, $priority));
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
     * Set event manager
     *
     * @param  EventManager $events
     * @return Queue
     */
    public function setEvents(EventManager $events): Queue
    {
        $this->events = $events;
        return $this;
    }

    /**
     * Get event manager
     *
     * @return ?EventManager
     */
    public function getEvents(): ?EventManager
    {
        return $this->events;
    }

    /**
     * Get event manager (alias)
     *
     * @return ?EventManager
     */
    public function events(): ?EventManager
    {
        return $this->events;
    }

    /**
     * Has event manager
     *
     * @return bool
     */
    public function hasEvents(): bool
    {
        return ($this->events !== null);
    }

    /**
     * Trigger a lifecycle event. Uses this Queue's own event manager if one
     * is set via setEvents(); otherwise falls back to $application's event
     * manager if one was passed in and has events registered; otherwise
     * does nothing. Never throws on its own account - if the resolved
     * manager's trigger() call throws (e.g. a listener's own code throws),
     * that exception propagates to the caller exactly as any other
     * uncaught exception would.
     *
     * @param  string       $name
     * @param  array        $params
     * @param  ?Application $application
     * @return void
     */
    protected function triggerEvent(string $name, array $params, ?Application $application = null): void
    {
        if ($this->hasEvents()) {
            $this->events->trigger($name, $params);
        } else if (($application !== null) && ($application->events() !== null)) {
            $application->events()->trigger($name, $params);
        }
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
            $reason = 'Exceeded max attempts or expired before execution';
            $this->adapter->bury($job, $reason);
            $this->triggerEvent('queue.job.buried', ['job' => $job, 'queue' => $this, 'reason' => $reason], $application);
            return $job;
        }

        $this->triggerEvent('queue.job.pre', ['job' => $job, 'queue' => $this], $application);

        $exception = null;
        $buried    = false;

        try {
            $this->runWithTimeout($job, $application);
            $job->complete();
            $this->adapter->delete($job);
        } catch (\Throwable $e) {
            $exception = $e;
            $job->failed($e->getMessage());
            if ($job->isValid()) {
                $this->adapter->release($job);
            } else {
                $buried = true;
                $this->adapter->bury($job, $e->getMessage());
            }
        }

        if ($exception === null) {
            $this->triggerEvent('queue.job.post', ['job' => $job, 'queue' => $this], $application);
        } else {
            $this->triggerEvent('queue.job.failed', ['job' => $job, 'queue' => $this, 'exception' => $exception], $application);
            if ($buried) {
                $this->triggerEvent('queue.job.buried', ['job' => $job, 'queue' => $this, 'reason' => $exception->getMessage()], $application);
            }
        }

        return $job;
    }

    /**
     * Run a job, enforcing its soft timeout when ext-pcntl is available.
     * Exec-type jobs are exempt from this pcntl-based alarm entirely -
     * AbstractJob::runExec() wires the job's timeout directly into
     * Symfony\Process's own setTimeout(), which is both more precise and
     * (unlike a pcntl alarm racing against a raw exec() call) actually
     * capable of killing the spawned child process on expiry. Layering a
     * second, competing pcntl alarm on top for the same job risks
     * interrupting Process's own internal wait()/kill logic mid-flight if
     * the alarm fires first, defeating the reliable-child-termination
     * guarantee this task exists to add.
     *
     * @param  AbstractJob  $job
     * @param  ?Application $application
     * @return mixed
     */
    protected function runWithTimeout(AbstractJob $job, ?Application $application): mixed
    {
        if (!$job->hasTimeout() || !extension_loaded('pcntl') || $job->hasExec()) {
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
     * Before running a due task, atomically claims it for the current
     * due-window via the adapter (claimTaskRun()) - if another worker
     * sharing the same adapter storage already claimed this task's window,
     * this call skips it silently rather than running it a second time.
     *
     * @param  array        $tasks         taskId => Task
     * @param  ?Application $application
     * @param  bool         $onlySubMinute
     * @return array  jobId => Task, for every task that ran this pass
     */
    public function evaluateTasksOnce(array $tasks, ?Application $application, bool $onlySubMinute = false): array
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

            $window = $isSubMinute ? (string)time() : (string)intdiv(time(), 60);
            if (!$this->adapter->claimTaskRun($taskId, $window)) {
                // Another worker already claimed this task's current
                // due-window - not a failure, just not ours to run.
                continue;
            }

            $this->triggerEvent('queue.task.pre', ['task' => $task, 'queue' => $this], $application);

            $exception = null;

            try {
                $task->run($application);
                $task->complete();
                if (!$isSubMinute) {
                    $this->adapter->updateTask($task);
                }
            } catch (\Exception $e) {
                $exception = $e;
                $task->failed($e->getMessage());
                if ($isSubMinute) {
                    $this->adapter->removeTask($taskId);
                    $this->adapter->schedule($task);
                    $this->adapter->claimTaskRun($taskId, $window);
                } else {
                    $this->adapter->updateTask($task);
                }
            }

            $ran[$task->getJobId()] = $task;

            if ($exception === null) {
                $this->triggerEvent('queue.task.post', ['task' => $task, 'queue' => $this], $application);
            } else {
                $this->triggerEvent('queue.task.failed', ['task' => $task, 'queue' => $this, 'exception' => $exception], $application);
            }
        }

        return $ran;
    }

    /**
     * Fetch every currently scheduled task from the adapter, once. Returns
     * an empty array if the adapter doesn't support tasks
     * (TaskAdapterInterface) or has none scheduled - callers don't need to
     * duplicate that guard.
     *
     * @return array  taskId => Task
     */
    public function getScheduledTasks(): array
    {
        if ((!($this->adapter instanceof TaskAdapterInterface)) || (!$this->adapter->hasTasks())) {
            return [];
        }

        $scheduledTasks = [];
        foreach ($this->adapter->getTasks() as $taskId) {
            $task = $this->adapter->getTask($taskId);
            if ($task instanceof Task) {
                $scheduledTasks[$taskId] = $task;
            }
        }

        return $scheduledTasks;
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

        $scheduledTasks = $this->getScheduledTasks();
        if (empty($scheduledTasks)) {
            return $tasks;
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
