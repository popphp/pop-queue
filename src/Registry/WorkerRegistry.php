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
namespace Pop\Queue\Registry;

use Pop\Event\Manager as EventManager;
use Pop\Queue\Queue;
use Pop\Queue\Worker;

/**
 * Worker registry class
 *
 * The read-side facade over a registry backend: who is running, which of
 * them have gone quiet, and which look genuinely stuck.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class WorkerRegistry
{

    /**
     * Registry backend
     * @var RegistryInterface
     */
    protected RegistryInterface $registry;

    /**
     * This process's own record, once registered. A WorkerRegistry instance
     * represents this process's view of the registry, which is what lets the
     * event listeners attached by attachTo() mutate the record by closing
     * over $this.
     * @var ?WorkerRecord
     */
    protected ?WorkerRecord $record = null;

    /**
     * Event managers this registry has already attached listeners to, keyed
     * by spl_object_id. resolveEventManager() returns the SAME manager for
     * every queue whenever the Application-level fallback is in play, and
     * Manager::on() is additive - without this guard, N queues would install
     * N copies of each listener on one manager and a single job would count
     * N times. It also makes repeated setRegistry()/attachTo() calls safe.
     * @var array
     */
    protected array $attached = [];

    /**
     * Constructor
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get the underlying backend
     *
     * @return RegistryInterface
     */
    public function getRegistry(): RegistryInterface
    {
        return $this->registry;
    }

    /**
     * Register this process, writing its record to the backend
     *
     * Note: when a Worker is given this registry, it calls this for you at
     * the right point in its lifecycle. Calling it directly opts you out of
     * that - the Worker will see an existing registration, take no
     * ownership, and never deregister it, so the record outlives the run and
     * is only reaped by prune().
     *
     * @param  ?string $name       optional operator-facing label
     * @param  array   $queueNames names of the queues being serviced
     * @param  string  $mode       WorkerRecord::MODE_DAEMON or MODE_SINGLE_PASS
     * @return WorkerRecord
     */
    public function register(?string $name = null, array $queueNames = [], string $mode = WorkerRecord::MODE_SINGLE_PASS): WorkerRecord
    {
        $this->record = WorkerRecord::create($name, $queueNames, $mode);
        $this->registry->write($this->record);

        return $this->record;
    }

    /**
     * Whether this process has registered
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return ($this->record !== null);
    }

    /**
     * This process's own record, or null if it hasn't registered
     *
     * @return ?WorkerRecord
     */
    public function getRecord(): ?WorkerRecord
    {
        return $this->record;
    }

    /**
     * Refresh this process's heartbeat and flush its record. A no-op when
     * not registered, so callers never need to guard.
     *
     * @return void
     */
    public function heartbeat(): void
    {
        if ($this->record === null) {
            return;
        }

        $this->record->touch();
        $this->registry->write($this->record);
    }

    /**
     * Remove this process's record. A no-op when not registered.
     *
     * @return void
     */
    public function deregister(): void
    {
        if ($this->record === null) {
            return;
        }

        $this->registry->delete($this->record->getId());
        $this->record = null;
    }

    /**
     * Every registered worker, keyed by worker ID
     *
     * @return array
     */
    public function getWorkers(): array
    {
        return $this->registry->all();
    }

    /**
     * A single worker by ID, or null
     *
     * @param  string $id
     * @return ?WorkerRecord
     */
    public function getWorker(string $id): ?WorkerRecord
    {
        return $this->registry->read($id);
    }

    /**
     * How many workers are registered
     *
     * @return int
     */
    public function countWorkers(): int
    {
        return count($this->registry->all());
    }

    /**
     * Workers whose heartbeat has gone quiet. Note this includes workers
     * that are merely busy inside a long job - a worker executing a job
     * cannot heartbeat, so use getStuckWorkers() to narrow to the ones
     * that look genuinely wedged.
     *
     * @param  int $seconds
     * @return array
     */
    public function getStaleWorkers(int $seconds = 90): array
    {
        return array_filter($this->registry->all(), function($record) use ($seconds) {
            return $record->isStale($seconds);
        });
    }

    /**
     * Workers that are stale AND holding a job that has outlived its own
     * timeout - the alerting signal
     *
     * @param  int $seconds
     * @return array
     */
    public function getStuckWorkers(int $seconds = 90): array
    {
        return array_filter($this->registry->all(), function($record) use ($seconds) {
            return $record->isLikelyStuck($seconds);
        });
    }

    /**
     * Remove records left behind by processes that are long gone
     *
     * @param  int $olderThanSeconds
     * @return int
     */
    public function prune(int $olderThanSeconds = 3600): int
    {
        return $this->registry->prune($olderThanSeconds);
    }

    /**
     * Wire this registry's current-job and counter tracking onto a worker's
     * queues, via the queue lifecycle events.
     *
     * Tracking rides on the existing events rather than new plumbing because
     * Queue::work() reserves AND runs a job internally - the Worker only
     * receives it after it ran, so it structurally cannot record the current
     * job before execution. queue.job.pre fires before execution, which is
     * exactly the hook needed.
     *
     * @param  Worker $worker
     * @return void
     */
    public function attachTo(Worker $worker): void
    {
        foreach ($worker->getQueues() as $queue) {
            $this->attachListeners($this->resolveEventManager($queue, $worker));
        }
    }

    /**
     * Attach this registry's listeners to one event manager, at most once
     * per manager for the lifetime of this registry instance.
     *
     * @param  EventManager $events
     * @return void
     */
    protected function attachListeners(EventManager $events): void
    {
        $id = spl_object_id($events);
        if (isset($this->attached[$id])) {
            return;
        }
        $this->attached[$id] = true;

        // Listener params are positional, not an array - Manager::trigger()
        // strips the keys before calling.
        $events->on('queue.job.pre', function($job, $queue) {
            if ($this->record !== null) {
                $this->record->setCurrentJob($job->getJobId(), $queue->getName(), $job->getTimeout());
                // Persisted BEFORE the job runs: a worker that wedges
                // mid-job can't write anything afterwards, so this is the
                // only chance to record what it died on.
                try {
                    $this->registry->write($this->record);
                } catch (\Throwable $e) {
                    // Best-effort - a failed write costs stuck-detection
                    // fidelity for this job, never the job itself.
                }
            }
        });

        $events->on('queue.job.post', function($job, $queue) {
            if ($this->record !== null) {
                $this->record->clearCurrentJob();
                $this->record->incrementProcessed();
                // Deliberately no write - the cleared state and counters
                // flush on the next heartbeat, keeping steady-state write
                // volume flat regardless of job throughput.
            }
        });

        $events->on('queue.job.failed', function($job, $queue, $exception) {
            if ($this->record !== null) {
                $this->record->clearCurrentJob();
                $this->record->incrementFailed();
            }
        });

        $events->on('queue.task.pre', function($task, $queue) {
            if ($this->record !== null) {
                $this->record->setCurrentJob($task->getJobId(), $queue->getName(), $task->getTimeout());
                try {
                    $this->registry->write($this->record);
                } catch (\Throwable $e) {
                    // Best-effort, as with jobs.
                }
            }
        });

        $events->on('queue.task.post', function($task, $queue) {
            if ($this->record !== null) {
                $this->record->clearCurrentJob();
                $this->record->incrementProcessed();
            }
        });

        $events->on('queue.task.failed', function($task, $queue, $exception) {
            if ($this->record !== null) {
                $this->record->clearCurrentJob();
                $this->record->incrementFailed();
            }
        });
    }

    /**
     * Find the event manager a queue's events actually reach, without
     * disturbing existing wiring.
     *
     * Queue::triggerEvent() uses the queue's own manager if set, else the
     * Application's - and setting a queue-level manager SUPPRESSES the
     * Application fallback. So attaching to the wrong one, or installing a
     * new one where a fallback was in play, would silently orphan a user's
     * app-level listeners.
     *
     * @param  Queue  $queue
     * @param  Worker $worker
     * @return EventManager
     */
    protected function resolveEventManager(Queue $queue, Worker $worker): EventManager
    {
        if ($queue->hasEvents()) {
            return $queue->events();
        }

        if ($worker->hasApplication() && ($worker->getApplication()->events() !== null)) {
            return $worker->getApplication()->events();
        }

        $events = new EventManager();
        $queue->setEvents($events);

        return $events;
    }

}
