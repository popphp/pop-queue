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

use ArrayIterator;
use Pop\Application;
use Pop\Event\Manager as EventManager;
use Pop\Queue\Process\AbstractJob;

/**
 * Queue worker class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Worker implements \ArrayAccess, \Countable, \IteratorAggregate
{

    /**
     * Queues
     * @var array
     */
    protected array $queues = [];

    /**
     * Queue weights, keyed by queue name. Higher services first. Not named
     * "priority" - that word is already used elsewhere in this codebase for
     * the unrelated FIFO/FILO adapter job-ordering setting.
     * @var array
     */
    protected array $weights = [];

    /**
     * Application object
     * @var ?Application
     */
    protected ?Application $application = null;

    /**
     * Event manager, for worker-level lifecycle observability hooks
     * (worker.work_loop.*, worker.run_loop.*). If not set, and this
     * Worker was constructed with an Application that has its own event
     * manager, that Application's manager is used instead - see
     * triggerEvent(). If neither is available, event firing is a silent
     * no-op.
     * @var ?EventManager
     */
    protected ?EventManager $events = null;

    /**
     * Whether a graceful shutdown has been requested, via stop() directly
     * or via a caught SIGTERM/SIGINT (see installSignalHandlers()).
     * workLoop()/runLoop() each reset this to false at their own start.
     * @var bool
     */
    protected bool $stopped = false;

    /**
     * Constructor
     *
     * Instantiate the queue worker object.
     *
     * @param mixed $queues
     * @param ?Application $application
     */
    public function __construct(mixed $queues = null, ?Application $application = null)
    {
        if (!empty($queues)) {
            if (is_array($queues)) {
                $this->addQueues($queues);
            } else if ($queues instanceof Queue) {
                $this->addQueue($queues);
            }
        }

        $this->application = $application;
    }

    /**
     * Create queue worker worker
     *
     * @param  mixed $queues
     * @param  ?Application $application
     * @return Worker
     */
    public static function create(mixed $queues = null, ?Application $application = null): Worker
    {
        return new self($queues, $application);
    }

    /**
     * Get the application
     *
     * @return ?Application
     */
    public function getApplication(): ?Application
    {
        return $this->application;
    }

    /**
     * Get the application (alias)
     *
     * @return ?Application
     */
    public function application(): ?Application
    {
        return $this->application;
    }

    /**
     * Has application
     *
     * @return bool
     */
    public function hasApplication(): bool
    {
        return ($this->application !== null);
    }

    /**
     * Set event manager
     *
     * @param  EventManager $events
     * @return Worker
     */
    public function setEvents(EventManager $events): Worker
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
     * Trigger a worker-level lifecycle event. Uses this Worker's own event
     * manager if one is set via setEvents(); otherwise falls back to the
     * event manager of the Application this Worker was constructed with,
     * if it has one; otherwise does nothing. Never throws on its own
     * account - if the resolved manager's trigger() call throws (e.g. a
     * listener's own code throws), that exception propagates to the
     * caller exactly as any other uncaught exception would.
     *
     * @param  string $name
     * @param  array  $params
     * @return void
     */
    protected function triggerEvent(string $name, array $params): void
    {
        if ($this->hasEvents()) {
            $this->events->trigger($name, $params);
        } else if (($this->application !== null) && ($this->application->events() !== null)) {
            $this->application->events()->trigger($name, $params);
        }
    }

    /**
     * Request a graceful shutdown of a running workLoop()/runLoop() call.
     * Takes effect at that loop's next iteration boundary - never mid-job
     * or mid-task-evaluation.
     *
     * @return Worker
     */
    public function stop(): Worker
    {
        $this->stopped = true;
        return $this;
    }

    /**
     * Whether a graceful shutdown has been requested
     *
     * @return bool
     */
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * Add queue
     *
     * @param  Queue $queue
     * @param  int   $weight
     * @return Worker
     */
    public function addQueue(Queue $queue, int $weight = 0): Worker
    {
        $this->queues[$queue->getName()]  = $queue;
        $this->weights[$queue->getName()] = $weight;
        return $this;
    }

    /**
     * Add queues
     *
     * @param  array $queues
     * @return Worker
     */
    public function addQueues(array $queues): Worker
    {
        foreach ($queues as $queue) {
            $this->addQueue($queue);
        }
        return $this;
    }

    /**
     * Get queues
     *
     * @return array
     */
    public function getQueues(): array
    {
        return $this->getQueuesByWeight();
    }

    /**
     * Get queues ordered by weight, highest first. PHP's sort functions
     * are stable since 8.0, so queues with equal weight (including the
     * default-zero case when no weight was ever set) keep their original
     * insertion order automatically.
     *
     * @return array
     */
    protected function getQueuesByWeight(): array
    {
        $queues = $this->queues;
        uksort($queues, function($a, $b) {
            return ($this->weights[$b] ?? 0) <=> ($this->weights[$a] ?? 0);
        });
        return $queues;
    }

    /**
     * Get queue
     *
     * @param  string $queue
     * @return ?Queue
     */
    public function getQueue(string $queue): ?Queue
    {
        return $this->queues[$queue] ?? null;
    }

    /**
     * Has queue
     *
     * @param  string $queue
     * @return bool
     */
    public function hasQueue(string $queue): bool
    {
        return (isset($this->queues[$queue]));
    }

    /**
     * Get a queue's weight (0 if never set)
     *
     * @param  string $queueName
     * @return int
     */
    public function getWeight(string $queueName): int
    {
        return $this->weights[$queueName] ?? 0;
    }

    /**
     * Work next job. Pass a queue name to work that specific queue (exactly
     * today's behavior). Pass nothing to try every registered queue in
     * weight order (highest first), returning the first job successfully
     * claimed - the highest-weight-first worker model, since workAll()
     * fans out to every queue regardless of weight and doesn't need this.
     *
     * @param  ?string $queueName
     * @return ?AbstractJob
     */
    public function work(?string $queueName = null): ?AbstractJob
    {
        if ($queueName !== null) {
            return isset($this->queues[$queueName]) ? $this->queues[$queueName]->work($this->application) : null;
        }

        foreach ($this->getQueuesByWeight() as $queue) {
            $job = $queue->work($this->application);
            if ($job !== null) {
                return $job;
            }
        }

        return null;
    }

    /**
     * Work next job across in all queues
     *
     * @return array
     */
    public function workAll(): array
    {
        $jobs = [];
        foreach ($this->getQueuesByWeight() as $queueName => $queue) {
            $jobs[$queueName] = $queue->work($this->application);
        }
        return $jobs;
    }

    /**
     * Run next scheduled task in queue
     *
     * @param  string $queueName
     * @return array
     */
    public function run(string $queueName): array
    {
        $tasks = [];
        if (isset($this->queues[$queueName])) {
            $tasks[$queueName] = $this->queues[$queueName]->run($this->application);
        }
        return $tasks;
    }

    /**
     * Run next scheduled task across all queues, fairly: every queue gets
     * one shared evaluation pass immediately, then - only if at least one
     * queue has a sub-minute task - up to 59 more shared passes (one per
     * second, sleeping once per tick, not once per queue per tick),
     * mirroring Queue::run()'s own pass-1-then-tick-loop shape one level
     * up. No single queue's tick-loop work can block another queue's
     * evaluation on the same tick.
     *
     * @return array
     */
    public function runAll(): array
    {
        $tasks         = [];
        $queueTaskSets = [];
        $queues        = $this->getQueuesByWeight();

        foreach ($queues as $queueName => $queue) {
            $tasks[$queueName]         = [];
            $queueTaskSets[$queueName] = $queue->getScheduledTasks();
        }

        foreach ($queueTaskSets as $queueName => $scheduledTasks) {
            foreach ($queues[$queueName]->evaluateTasksOnce($scheduledTasks, $this->application) as $jobId => $task) {
                $tasks[$queueName][$jobId] = $task;
            }
        }

        $hasSubMinute = false;
        foreach ($queueTaskSets as $scheduledTasks) {
            foreach ($scheduledTasks as $task) {
                if ($task->cron()->hasSeconds()) {
                    $hasSubMinute = true;
                    break 2;
                }
            }
        }

        if ($hasSubMinute) {
            for ($tick = 1; $tick < 60; $tick++) {
                sleep(1);
                foreach ($queueTaskSets as $queueName => $scheduledTasks) {
                    foreach ($queues[$queueName]->evaluateTasksOnce($scheduledTasks, $this->application, true) as $jobId => $task) {
                        $tasks[$queueName][$jobId] = $task;
                    }
                }
            }
        }

        return $tasks;
    }

    /**
     * Install SIGTERM/SIGINT handlers that request a graceful stop(),
     * rather than letting the OS terminate the process immediately. A
     * no-op when ext-pcntl isn't loaded - workLoop()/runLoop() still run
     * correctly without it, just without OS-signal-based shutdown
     * available (only stop() can end them in that case).
     *
     * Unlike Queue::runWithTimeout()'s SIGALRM handler next door, these
     * handlers are never restored to SIG_DFL once installed - not even
     * after workLoop()/runLoop() returns. This is a deliberate, accepted
     * tradeoff for this pass rather than an oversight: a second
     * SIGTERM/SIGINT sent after a loop has already ended is simply inert
     * (caught by this handler and ignored) instead of terminating the
     * process via the OS default.
     *
     * @return void
     */
    protected function installSignalHandlers(): void
    {
        if (!extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, function() {
            $this->stop();
        });
        pcntl_signal(SIGINT, function() {
            $this->stop();
        });
    }

    /**
     * Work jobs across all registered queues, forever, until stopped.
     * Calls workAll() every iteration; sleeps $sleepSeconds only when a
     * full pass finds nothing anywhere (every queue returned null),
     * looping again immediately otherwise. Stoppable via stop() directly
     * or, when ext-pcntl is loaded, via SIGTERM/SIGINT - either way, the
     * current iteration's job is never torn down mid-execution and its
     * remaining code always runs to completion - though a blocking call
     * inside that code (e.g. sleep()) can itself be interrupted early if
     * a signal lands during it; see README's Daemon mode section.
     * A negative $sleepSeconds is silently clamped to 0.
     *
     * @param  int $sleepSeconds
     * @return void
     */
    public function workLoop(int $sleepSeconds = 1): void
    {
        $sleepSeconds  = max(0, $sleepSeconds);
        $this->stopped = false;
        $this->installSignalHandlers();

        while (!$this->stopped) {
            $jobs = $this->workAll();

            $anyWorked = false;
            foreach ($jobs as $job) {
                if ($job !== null) {
                    $anyWorked = true;
                    break;
                }
            }

            $this->triggerEvent('worker.work_loop.tick', ['jobs' => $jobs, 'worker' => $this]);

            if ($this->stopped) {
                break;
            }

            if (!$anyWorked) {
                $this->triggerEvent('worker.work_loop.idle', ['worker' => $this]);
                sleep($sleepSeconds);
            }
        }

        $this->triggerEvent('worker.work_loop.shutdown', ['worker' => $this]);
    }

    /**
     * Run scheduled tasks across all registered queues, forever, until
     * stopped. Calls runAll() every iteration; sleeps $sleepSeconds only
     * when a full pass finds nothing due anywhere, looping again
     * immediately otherwise. runAll() already blocks appropriately on its
     * own whenever a sub-minute task exists, so this backoff sleep only
     * ever triggers on the coarse-only-or-nothing-scheduled case, which
     * returns near-instantly and would otherwise busy-loop. A stop signal
     * arriving mid-runAll() (during its own internal sub-minute tick loop)
     * isn't noticed until that call returns - see the design spec's
     * Non-goals for why this latency is accepted rather than fixed here.
     * A negative $sleepSeconds is silently clamped to 0.
     *
     * @param  int $sleepSeconds
     * @return void
     */
    public function runLoop(int $sleepSeconds = 1): void
    {
        $sleepSeconds  = max(0, $sleepSeconds);
        $this->stopped = false;
        $this->installSignalHandlers();

        while (!$this->stopped) {
            $tasks = $this->runAll();

            $anyRan = false;
            foreach ($tasks as $queueTasks) {
                if (!empty($queueTasks)) {
                    $anyRan = true;
                    break;
                }
            }

            $this->triggerEvent('worker.run_loop.tick', ['tasks' => $tasks, 'worker' => $this]);

            if ($this->stopped) {
                break;
            }

            if (!$anyRan) {
                $this->triggerEvent('worker.run_loop.idle', ['worker' => $this]);
                sleep($sleepSeconds);
            }
        }

        $this->triggerEvent('worker.run_loop.shutdown', ['worker' => $this]);
    }

    /**
     * Clear jobs from queue
     *
     * @param  string $queueName
     * @return Worker
     */
    public function clear(string $queueName): Worker
    {
        if (isset($this->queues[$queueName])) {
            $this->queues[$queueName]->clear();
        }
        return $this;
    }

    /**
     * Clear failed jobs from queue
     *
     * @param  string $queueName
     * @return Worker
     */
    public function clearFailed(string $queueName): Worker
    {
        if (isset($this->queues[$queueName])) {
            $this->queues[$queueName]->clearFailed();
        }
        return $this;
    }

    /**
     * Clear tasks from queue
     *
     * @param  string $queueName
     * @return Worker
     */
    public function clearTasks(string $queueName): Worker
    {
        if (isset($this->queues[$queueName])) {
            $this->queues[$queueName]->clearTasks();
        }
        return $this;
    }

    /**
     * Clear all jobs from queues
     *
     * @return Worker
     */
    public function clearAll(): Worker
    {
        foreach ($this->getQueuesByWeight() as $queue) {
            $queue->clear();
        }
        return $this;
    }

    /**
     * Clear all failed jobs from queues
     *
     * @return Worker
     */
    public function clearAllFailed(): Worker
    {
        foreach ($this->getQueuesByWeight() as $queue) {
            $queue->clearFailed();
        }
        return $this;
    }

    /**
     * Clear all tasks from queues
     *
     * @return Worker
     */
    public function clearAllTasks(): Worker
    {
        foreach ($this->getQueuesByWeight() as $queue) {
            $queue->clearTasks();
        }
        return $this;
    }

    /**
     * Register a queue with the worker
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->addQueue($value);
    }

    /**
     * Get a queue
     *
     * @param  string $name
     * @return ?Queue
     */
    public function __get(string $name): ?Queue
    {
        return $this->getQueue($name);
    }

    /**
     * Determine if a queue is registered with the worker object
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->queues[$name]);
    }

    /**
     * Unset a queue with the worker
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        if (isset($this->queues[$name])) {
            unset($this->queues[$name], $this->weights[$name]);
        }
    }

    /**
     * Set a queue with the worker
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * Get a queue
     *
     * @param  mixed $offset
     * @return ?Queue
     */
    public function offsetGet(mixed $offset): ?Queue
    {
        return $this->__get($offset);
    }

    /**
     * Determine if a queue is registered with the worker object
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * Unset a queue from the worker
     *
     * @param  string $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->__unset($offset);
    }

    /**
     * Return count
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->queues);
    }

    /**
     * Get iterator
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->getQueuesByWeight());
    }

}
