<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor;

use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs\AbstractJob;

/**
 * Worker class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
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
     * Worker jobs
     * @var AbstractJob[]
     */
    protected $jobs = [];

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
     * @param  AbstractJob $job
     * @return Worker
     */
    public function addJob(AbstractJob $job)
    {
        if ($this->isFilo()) {
            array_unshift($this->jobs, $job);
        } else {
            $this->jobs[] = $job;
        }
        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @return Worker
     */
    public function addJobs(array $jobs)
    {
        foreach ($jobs as $job) {
            $this->addJob($job);
        }
        return $this;
    }

    /**
     * Get jobs
     *
     * @return array
     */
    public function getJobs()
    {
        return $this->jobs;
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
     * Has jobs
     *
     * @return boolean
     */
    public function hasJobs()
    {
        return (count($this->jobs) > 0);
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
     * @param  Queue $queue
     * @return int
     */
    public function processNext(Queue $queue = null)
    {
        $nextIndex = $this->getNextIndex();

        if ($this->hasJob($nextIndex)) {
            try {
                $application = ((null !== $queue) && (null !== $queue->hasApplication())) ? $queue->application() : null;
                $this->results[$nextIndex] = $this->jobs[$nextIndex]->run($application);
                $this->jobs[$nextIndex]->setAsCompleted();
                $this->completed[$nextIndex] = $this->jobs[$nextIndex];

                if ((null !== $queue) && ($this->jobs[$nextIndex]->hasJobId()) &&
                    ($queue->adapter()->hasJob($this->jobs[$nextIndex]->getJobId()))) {
                    $queue->adapter()->updateJob($this->jobs[$nextIndex]->getJobId(), true, true);
                }
            } catch (\Exception $e) {
                $this->jobs[$nextIndex]->setAsFailed();
                $this->failed[$nextIndex]           = $this->jobs[$nextIndex];
                $this->failedExceptions[$nextIndex] = $e;
                if ((null !== $queue) && ($this->failed[$nextIndex]->hasJobId()) &&
                    ($queue->adapter()->hasJob($this->failed[$nextIndex]->getJobId()))) {
                    $queue->adapter()->failed($queue->getName(), $this->failed[$nextIndex]->getJobId(), $e);
                }
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