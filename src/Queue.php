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
     * Queue workers
     * @var array
     */
    protected $workers = [];

    /**
     * Queue schedules
     * @var array
     */
    protected $schedules = [];

    /**
     * Constructor
     *
     * Instantiate the queue object
     *
     * @param  Adapter\AdapterInterface $adapter
     */
    public function __construct(Adapter\AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
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
     * Add a worker
     *
     * @param  Process\Worker $worker
     * @return Queue
     */
    public function addWorker(Process\Worker $worker)
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
     * Add a schedule
     *
     * @param  Process\Schedule $schedule
     * @return Queue
     */
    public function addSchedule(Process\Schedule $schedule)
    {
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

}