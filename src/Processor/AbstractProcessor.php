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
use Pop\Queue\Processor\Job\AbstractJob;

/**
 * Abstract process class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
abstract class AbstractProcessor implements ProcessorInterface
{

    /**
     * The queue the processor belongs to
     * @var Queue
     */
    protected $queue = null;

    /**
     * Worker jobs
     * @var array
     */
    protected $jobs = [];

    /**
     * Failed jobs
     * @var array
     */
    protected $failed = [];

    /**
     * Failed jobs exceptions
     * @var array
     */
    protected $failedExceptions = [];

    /**
     * Constructor
     *
     * Instantiate the processor object
     *
     * @param  Queue $queue
     */
    public function __construct(Queue $queue = null)
    {
        if (null !== $queue) {
            $this->setQueue($queue);
        }
    }

    /**
     * Set queue
     *
     * @param  Queue $queue
     * @return AbstractProcessor
     */
    public function setQueue(Queue $queue)
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Get queue
     *
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Has queue
     *
     * @return boolean
     */
    public function hasQueue()
    {
        return (null !== $this->queue);
    }

    /**
     * Add job
     *
     * @param  Job\AbstractJob $job
     * @return Worker
     */
    abstract public function addJob(Job\AbstractJob $job);

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @return AbstractProcessor
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
     * @return Job\AbstractJob
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
     * Get failed jobs
     *
     * @return array
     */
    public function getFailedJobs()
    {
        return $this->failed;
    }

    /**
     * Get failed job
     *
     * @param  mixed $index
     * @return AbstractJob
     */
    public function getFailedJob($index)
    {
        return (isset($this->failed[$index])) ? $this->failed[$index] : null;
    }

    /**
     * Has failed jobs
     *
     * @return boolean
     */
    public function hasFailedJobs()
    {
        return !empty($this->failed);
    }

    /**
     * Get failed exceptions
     *
     * @return array
     */
    public function getFailedExceptions()
    {
        return $this->failedExceptions;
    }

    /**
     * Get failed exception
     *
     * @param  mixed $index
     * @return AbstractJob
     */
    public function getFailedException($index)
    {
        return (isset($this->failedExceptions[$index])) ? $this->failedExceptions[$index] : null;
    }

    /**
     * Has failed exceptions
     *
     * @return boolean
     */
    public function hasFailedExceptions()
    {
        return !empty($this->failedExceptions);
    }

    /**
     * Process next job
     *
     * @return boolean
     */
    abstract public function processNext();

}
