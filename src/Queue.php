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
use Pop\Queue\Processor;
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

            foreach ($jobs as $job) {
                if ($job['priority'] == Processor\Worker::FILO) {
                    $filoWorker->addJob($job['payload']);
                } else {
                    $fifoWorker->addJob($job['payload']);
                }
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
     * Push all jobs to queue adapter (alias)
     *
     * @return array
     */
    public function pushAll(): array
    {
        return $this->pushWorkers();
    }

    /**
     * Process schedulers in the queue
     *
     * @return array
     */
    public function processWorkers(): array
    {
        $results = [];

        if ($this->hasWorkers()) {
            foreach ($this->workers as $worker) {
                while ($worker->hasNextJob()) {
                    $worker->processNext($this);
                }
                if ($worker->hasJobResults()) {
                    $results = array_merge($results, $worker->getJobResults());
                }
            }
        }

        return $results;
    }

    /**
     * Process all schedulers and workers in the queue (alias)
     *
     * @return array
     */
    public function processAll(): array
    {
        return $this->processWorkers();
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