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
namespace Pop\Queue\Process;

/**
 * Worker class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Worker extends AbstractProcess
{


    /**
     * Worker priority constants
     */
    const FIFO = 'FIFO'; // Same as LILO
    const FILO = 'FILO'; // Same as LIFO

    /**
     * Worker type
     * @var array
     */
    protected $priority = 'FIFO';

    /**
     * Constructor
     *
     * Instantiate the worker object
     *
     * @param  string $priority
     */
    public function __construct($priority = 'FIFO')
    {
        $this->setPriority($priority);
    }

    /**
     * Set worker priority
     *
     * @param  string $priority
     * @return Worker
     */
    public function setPriority($priority = 'FIFO')
    {
        if (defined('self::' . $priority)) {
            $this->priority = $priority;
        }
        return $this;
    }

    /**
     * Add job
     *
     * @param  AbstractJob $job
     * @return Worker
     */
    public function addJob(AbstractJob $job)
    {
        $this->jobs[] = $job;
        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @return AbstractProcess
     */
    public function addJobs(array $jobs)
    {
        foreach ($jobs as $job) {
            $this->addJob($job);
        }
        return $this;
    }

    /**
     * Get job
     *
     * @param  int $index
     * @return AbstractJob
     */
    public function getJob($index)
    {
        return (isset($this->jobs[$index])) ? $this->jobs[$index] : null;
    }

    /**
     * Has job
     *
     * @param  int $index
     * @return boolean
     */
    public function hasJob($index)
    {
        return (isset($this->jobs[$index]));
    }

}