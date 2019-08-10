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
     * @param  Adapter\AdapterInterface $adapter
     * @param  Application              $application
     */
    public function __construct(Adapter\AdapterInterface $adapter, Application $application = null)
    {
        $this->adapter     = $adapter;
        $this->application = $application;
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
        if (!$worker->hasQueue()) {
            $worker->setQueue($this);
        }
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
        if (!$scheduler->hasQueue()) {
            $scheduler->setQueue($this);
        }
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

}