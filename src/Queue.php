<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Queue
{

    /**
     * Queue name
     * @var string
     */
    protected $name = null;

    /**
     * Queue adapter
     * @var AdapterInterface
     */
    protected $adapter = null;

    /**
     * Application object
     * @var Application
     */
    protected $application = null;

    /**
     * Queue workers
     * @var Processor\Worker[]
     */
    protected $workers = [];

    /**
     * Queue schedulers
     * @var Processor\Scheduler[]
     */
    protected $schedulers = [];

    /**
     * Constructor
     *
     * Instantiate the queue object
     *
     * @param  string                   $name
     * @param  Adapter\AdapterInterface $adapter
     * @param  Application              $application
     */
    public function __construct($name, Adapter\AdapterInterface $adapter, Application $application = null)
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
     * @param  Application              $application
     * @return Queue
     */
    public static function load($name, Adapter\AdapterInterface $adapter, Application $application = null)
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
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the adapter
     *
     * @return AdapterInterface
     */
    public function adapter()
    {
        return $this->adapter;
    }

    /**
     * Get the application
     *
     * @return Application
     */
    public function application()
    {
        return $this->application;
    }

    /**
     * Has application
     *
     * @return boolean
     */
    public function hasApplication()
    {
        return (null !== $this->application);
    }

    /**
     * Add a worker
     *
     * @param  Processor\Worker $worker
     * @return Queue
     */
    public function addWorker(Processor\Worker $worker)
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
    public function addWorkers(array $workers)
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
    public function getWorkers()
    {
        return $this->workers;
    }

    /**
     * Has workers
     *
     * @return boolean
     */
    public function hasWorkers()
    {
        return !empty($this->workers);
    }

    /**
     * Add a scheduler
     *
     * @param  Processor\Scheduler $scheduler
     * @return Queue
     */
    public function addScheduler(Processor\Scheduler $scheduler)
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
    public function addSchedulers(array $schedulers)
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
    public function getSchedulers()
    {
        return $this->schedulers;
    }

    /**
     * Has schedulers
     *
     * @return boolean
     */
    public function hasSchedulers()
    {
        return !empty($this->schedulers);
    }

    /**
     * Push scheduled jobs to queue adapter
     *
     * @return Queue
     */
    public function pushSchedulers()
    {
        foreach ($this->schedulers as $scheduler) {
            if ($scheduler->hasSchedules()) {
                foreach ($scheduler->getSchedules() as $schedule) {
                    $this->adapter->push($this, $schedule);
                }
            }
        }

        return $this;
    }

    /**
     * Push worker jobs to queue adapter
     *
     * @return Queue
     */
    public function pushWorkers()
    {
        foreach ($this->workers as $worker) {
            if ($worker->hasJobs()) {
                foreach ($worker->getJobs() as $job) {
                    $this->adapter->push($this, $job, $worker->getPriority());
                }
            }
        }

        return $this;
    }

    /**
     * Push all jobs to queue adapter
     *
     * @return Queue
     */
    public function pushAll()
    {
        $this->pushSchedulers();
        $this->pushWorkers();

        return $this;
    }

    /**
     * Process schedulers in the queue
     *
     * @return Queue
     */
    public function processSchedulers()
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
    public function processWorkers()
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
    public function processAll()
    {
        $this->processSchedulers();
        $this->processWorkers();

        return $this;
    }

    /**
     * Check if queue has jobs
     *
     * @return boolean
     */
    public function hasJobs()
    {
        return $this->adapter->hasJobs($this->name);
    }

    /**
     * Get queue jobs
     *
     * @return array
     */
    public function getJobs()
    {
        return $this->adapter->getJobs($this->name);
    }

    /**
     * Check if queue has completed jobs
     *
     * @return boolean
     */
    public function hasCompletedJobs()
    {
        return $this->adapter->hasCompletedJobs($this->name);
    }

    /**
     * Get queue completed jobs
     *
     * @return array
     */
    public function getCompletedJobs()
    {
        return $this->adapter->getCompletedJobs($this->name);
    }

    /**
     * Check if queue adapter has failed jobs
     *
     * @return boolean
     */
    public function hasFailedJobs()
    {
        return $this->adapter->hasFailedJobs($this->name);
    }

    /**
     * Get queue jobs
     *
     * @return array
     */
    public function getFailedJobs()
    {
        return $this->adapter->getFailedJobs($this->name);
    }

    /**
     * Clear jobs off of the queue stack
     *
     * @param  boolean $all
     * @return void
     */
    public function clear($all = false)
    {
        $this->adapter->clear($this->name, $all);
    }

    /**
     * Clear failed jobs off of the queue stack
     *
     * @return void
     */
    public function clearFailed()
    {
        $this->adapter->clearFailed($this->name);
    }

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  boolean $all
     * @return void
     */
    public function flush($all = false)
    {
        $this->adapter->flush($all);
    }

    /**
     * Flush all failed jobs off of the queue stack
     *
     * @return void
     */
    public function flushFailed()
    {
        $this->adapter->flushFailed();
    }

}