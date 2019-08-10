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
     * Queue schedules
     * @var Processor\Schedule[]
     */
    protected $schedules = [];

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
     * Add a schedule
     *
     * @param  Processor\Schedule $schedule
     * @return Queue
     */
    public function addSchedule(Processor\Schedule $schedule)
    {
        if (!$schedule->hasQueue()) {
            $schedule->setQueue($this);
        }
        $this->schedules[] = $schedule;
        return $this;
    }

    /**
     * Add schedules
     *
     * @param  array $schedules
     * @return Queue
     */
    public function addSchedules(array $schedules)
    {
        foreach ($schedules as $schedule) {
            $this->addSchedule($schedule);
        }
        return $this;
    }

    /**
     * Get schedules
     *
     * @return array
     */
    public function getSchedules()
    {
        return $this->schedules;
    }

    /**
     * Has schedules
     *
     * @return boolean
     */
    public function hasSchedules()
    {
        return !empty($this->schedules);
    }

}