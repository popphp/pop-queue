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
     * @param  boolean          $loaded
     * @return Queue
     */
    public function addWorker(Processor\Worker $worker, $loaded = false)
    {
        if (!$worker->hasQueue()) {
            $worker->setQueue($this);
        }
        $this->workers[] = $worker;

        if (!($loaded) && ($worker->hasJobs())) {
            foreach ($worker->getJobs() as $job) {
                $this->adapter->push($this->name, $job);
            }
        }

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
     * @param  boolean             $loaded
     * @return Queue
     */
    public function addScheduler(Processor\Scheduler $scheduler, $loaded = false)
    {
        if (!$scheduler->hasQueue()) {
            $scheduler->setQueue($this);
        }
        $this->schedulers[] = $scheduler;

        if (!($loaded) && ($scheduler->hasSchedules())) {
            foreach ($scheduler->getSchedules() as $schedule) {
                $this->adapter->push($this->name, $schedule);
            }
        }

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
     * Process schedulers in the queue
     *
     * @return Queue
     */
    public function processSchedulers()
    {
        if ($this->hasSchedulers()) {
            foreach ($this->schedulers as $scheduler) {
                $scheduler->processNext();
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
                    $worker->processNext();
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

}