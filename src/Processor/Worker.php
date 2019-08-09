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
namespace Pop\Queue\Processor;

use Pop\Queue\Queue;
use Pop\Queue\Processor\Job;

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
class Worker extends AbstractProcessor
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
     * @param  Queue  $queue
     */
    public function __construct($priority = 'FIFO', Queue $queue = null)
    {
        parent::__construct($queue);
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
        if (!$job->hasProcessor()) {
            $job->setProcessor($this);
        }
        if ($this->isFilo()) {
            array_unshift($this->jobs, $job);
        } else {
            $this->jobs[] = $job;
        }
        return $this;
    }

    /**
     * Has next job
     *
     * @return boolean
     */
    public function hasNextJob()
    {
        $current = key($this->jobs);
        return ((null !== $current) && ($current < count($this->jobs)));
    }

    /**
     * Process next job
     *
     * @return int
     */
    public function processNext()
    {
        $nextIndex = $this->getNextIndex();
        if ($this->hasJob($nextIndex)) {
            try {
                $this->jobs[$nextIndex]->run();
                $this->jobs[$nextIndex]->setAsCompleted();
            } catch (\Exception $e) {
                $this->jobs[$nextIndex]->setAsFailed();
                $this->failed[$nextIndex]           = $this->jobs[$nextIndex];
                $this->failedExceptions[$nextIndex] = $e;
            }
        }
        return $nextIndex;
    }

    /**
     * Get next index
     *
     * @return int
     */
    public function getNextIndex()
    {
        $index = key($this->jobs);
        next($this->jobs);
        return $index;
    }

}