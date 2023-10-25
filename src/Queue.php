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
namespace Pop\Queue;

use Pop\Queue\Adapter\AdapterInterface;
use Pop\Queue\Processor\Jobs;
use Pop\Application;

/**
 * Queue class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Queue
{

    /**
     * Queue name
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Queue adapter
     * @var ?AdapterInterface
     */
    protected ?AdapterInterface $adapter = null;

    /**
     * Application object
     * @var ?Application
     */
    protected ?Application $application = null;

    /**
     * Queue workers
     * @var array
     */
    protected array $workers = [];

    /**
     * Queue schedulers
     * @var array
     */
    protected array $schedulers = [];

    /**
     * Constructor
     *
     * Instantiate the queue object
     *
     * @param  string                   $name
     * @param  Adapter\AdapterInterface $adapter
     * @param  ?Application             $application
     */
    public function __construct(string $name, Adapter\AdapterInterface $adapter, ?Application $application = null)
    {
        $this->name        = $name;
        $this->adapter     = $adapter;
        $this->application = $application;
    }

    /**
     * Load queue from adapter
     *
     * @param  string                   $name
     * @param  Adapter\AdapterInterface $adapter
     * @param  ?Application             $application
     * @return Queue
     */
    public static function load(string $name, Adapter\AdapterInterface $adapter, ?Application $application = null): Queue
    {
        $queue = new static($name, $adapter, $application);

        if ($adapter->hasJobs($name)) {
            $jobs       = $adapter->getJobs($name);
            $fifoWorker = new Processor\Worker();
            $filoWorker = new Processor\Worker(Processor\Worker::FILO);
            $scheduler  = new Processor\Scheduler();

            foreach ($jobs as $job) {
                if ($job['payload'] instanceof Jobs\Schedule) {
                    $scheduler->addSchedule($job['payload']);
                } else if ($job['priority'] == Processor\Worker::FILO) {
                    $filoWorker->addJob($job['payload']);
                } else {
                    $fifoWorker->addJob($job['payload']);
                }
            }

            if ($scheduler->hasSchedules()) {
                $queue->addScheduler($scheduler);
            }
            if ($fifoWorker->hasJobs()) {
                $queue->addWorker($fifoWorker);
            }
            if ($filoWorker->hasJobs()) {
                $queue->addWorker($filoWorker);
            }
        }

        return $queue;
    }

    /**
     * Get the queue name
     *
     * @return ?string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get the adapter
     *
     * @return ?AdapterInterface
     */
    public function adapter(): ?AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get the application
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
     * Add a worker
     *
     * @param  Processor\Worker $worker
     * @return Queue
     */
    public function addWorker(Processor\Worker $worker): Queue
    {
        $this->workers[] = $worker;
        return $this;
    }

    /**
     * Add workers
     *
     * @param  array $workers
     * @return Queue
     */
    public function addWorkers(array $workers): Queue
    {
        foreach ($workers as $worker) {
            $this->addWorker($worker);
        }

        return $this;
    }

    /**
     * Get workers
     *
     * @return array
     */
    public function getWorkers(): array
    {
        return $this->workers;
    }

    /**
     * Has workers
     *
     * @return bool
     */
    public function hasWorkers(): bool
    {
        return !empty($this->workers);
    }

    /**
     * Add a scheduler
     *
     * @param  Processor\Scheduler $scheduler
     * @return Queue
     */
    public function addScheduler(Processor\Scheduler $scheduler): Queue
    {
        $this->schedulers[] = $scheduler;
        return $this;
    }

    /**
     * Add schedulers
     *
     * @param  array $schedulers
     * @return Queue
     */
    public function addSchedulers(array $schedulers): Queue
    {
        foreach ($schedulers as $scheduler) {
            $this->addScheduler($scheduler);
        }

        return $this;
    }

    /**
     * Get schedulers
     *
     * @return array
     */
    public function getSchedulers(): array
    {
        return $this->schedulers;
    }

    /**
     * Has schedulers
     *
     * @return bool
     */
    public function hasSchedulers(): bool
    {
        return !empty($this->schedulers);
    }

    /**
     * Push scheduled jobs to queue adapter
     *
     * @return array
     */
    public function pushSchedulers(): array
    {
        $pushed = [];

        foreach ($this->schedulers as $scheduler) {
            if ($scheduler->hasSchedules()) {
                foreach ($scheduler->getSchedules() as $schedule) {
                    $jobId = $this->adapter->push($this, $schedule);
                    if (!empty($jobId)) {
                        $pushed[$jobId] = $schedule->getJob()->getJobDescription();
                    }
                }
            }
        }

        return $pushed;
    }

    /**
     * Push worker jobs to queue adapter
     *
     * @return array
     */
    public function pushWorkers(): array
    {
        $pushed = [];

        foreach ($this->workers as $worker) {
            if ($worker->hasJobs()) {
                foreach ($worker->getJobs() as $job) {
                    $jobId = $this->adapter->push($this, $job, $worker->getPriority());
                    if (!empty($jobId)) {
                        $pushed[$jobId] = $job->getJobDescription();
                    }
                }
            }
        }

        return $pushed;
    }

    /**
     * Push all jobs to queue adapter
     *
     * @return array
     */
    public function pushAll(): array
    {
        $pushedScheduled = $this->pushSchedulers();
        $pushedProcessed = $this->pushWorkers();

        return $pushedScheduled + $pushedProcessed;
    }

    /**
     * Process schedulers in the queue
     *
     * @return Queue
     */
    public function processSchedulers(): Queue
    {
        if ($this->hasSchedulers()) {
            foreach ($this->schedulers as $scheduler) {
                $scheduler->processNext($this);
            }
        }

        return $this;
    }

    /**
     * Process schedulers in the queue
     *
     * @return Queue
     */
    public function processWorkers(): Queue
    {
        if ($this->hasWorkers()) {
            foreach ($this->workers as $worker) {
                while ($worker->hasNextJob()) {
                    $worker->processNext($this);
                }
            }
        }

        return $this;
    }

    /**
     * Process all schedulers and workers in the queue
     *
     * @return Queue
     */
    public function processAll(): Queue
    {
        $this->processSchedulers();
        $this->processWorkers();

        return $this;
    }

    /**
     * Check if job is queued, but hasn't run yet
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function isQueued(mixed $jobId): bool
    {
        return (($this->adapter->hasJob($jobId)) && (!$this->adapter->hasCompletedJob($jobId)) &&
            (!$this->adapter->hasFailedJob($jobId)));
    }

    /**
     * Check if job is completed (alias)
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function isCompleted(mixed $jobId): bool
    {
        return $this->adapter->hasCompletedJob($jobId);
    }

    /**
     * Check if job has failed (alias)
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasFailed(mixed $jobId): bool
    {
        return $this->adapter->hasFailedJob($jobId);
    }

    /**
     * Check if queue has job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasJob(mixed $jobId): bool
    {
        return $this->adapter->hasJob($jobId);
    }

    /**
     * Get job
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getJob(mixed $jobId, bool $unserialize = true): array
    {
        return $this->adapter->getJob($jobId, $unserialize);
    }

    /**
     * Check if queue has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return $this->adapter->hasJobs($this->name);
    }

    /**
     * Get queue jobs
     *
     * @return array
     */
    public function getJobs(): array
    {
        return $this->adapter->getJobs($this->name);
    }

    /**
     * Check if queue has completed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasCompletedJob(mixed $jobId): bool
    {
        return $this->adapter->hasCompletedJob($jobId);
    }

    /**
     * Get completed job
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJob(mixed $jobId, bool $unserialize = true): array
    {
        return $this->adapter->getCompletedJob($jobId, $unserialize);
    }

    /**
     * Check if queue has completed jobs
     *
     * @return bool
     */
    public function hasCompletedJobs(): bool
    {
        return $this->adapter->hasCompletedJobs($this->name);
    }

    /**
     * Get queue completed jobs
     *
     * @return array
     */
    public function getCompletedJobs(): array
    {
        return $this->adapter->getCompletedJobs($this->name);
    }

    /**
     * Check if queue has failed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasFailedJob(mixed $jobId): bool
    {
        return $this->adapter->hasFailedJob($jobId);
    }

    /**
     * Get failed job
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJob(mixed $jobId, bool $unserialize = true): array
    {
        return $this->adapter->getFailedJob($jobId, $unserialize);
    }

    /**
     * Check if queue adapter has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool
    {
        return $this->adapter->hasFailedJobs($this->name);
    }

    /**
     * Get queue failed jobs
     *
     * @return array
     */
    public function getFailedJobs(): array
    {
        return $this->adapter->getFailedJobs($this->name);
    }

    /**
     * Clear jobs off of the queue stack
     *
     * @param  bool $all
     * @return void
     */
    public function clear(bool $all = false): void
    {
        $this->adapter->clear($this->name, $all);
    }

    /**
     * Clear failed jobs off of the queue stack
     *
     * @return void
     */
    public function clearFailed(): void
    {
        $this->adapter->clearFailed($this->name);
    }

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  bool $all
     * @return void
     */
    public function flush(bool $all = false): void
    {
        $this->adapter->flush($all);
    }

    /**
     * Flush all failed jobs off of the queue stack
     *
     * @return void
     */
    public function flushFailed(): void
    {
        $this->adapter->flushFailed();
    }

    /**
     * Flush all pop queue items
     *
     * @return void
     */
    public function flushAll(): void
    {
        $this->adapter->flushAll();
    }

}