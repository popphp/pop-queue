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

use Pop\Queue\Process\Job;

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
     * @var string
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
     * Get worker priority
     *
     * @return string
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * Is worker fifo
     *
     * @return boolean
     */
    public function isFifo()
    {
        return ($this->priority == self::FIFO);
    }

    /**
     * Is worker filo
     *
     * @return boolean
     */
    public function isFilo()
    {
        return ($this->priority == self::FILO);
    }

    /**
     * Add job
     *
     * @param  Job\AbstractJob $job
     * @return Worker
     */
    public function addJob(Job\AbstractJob $job)
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
     * @return Job\AbstractJob
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