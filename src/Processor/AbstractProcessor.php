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
use Pop\Queue\Processor\Jobs\AbstractJob;

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
     * Job results
     * @var array
     */
    protected $results = [];

    /**
     * Completed jobs
     * @var AbstractJob[]
     */
    protected $completed = [];

    /**
     * Failed jobs
     * @var AbstractJob[]
     */
    protected $failed = [];

    /**
     * Failed jobs exceptions
     * @var \Exception[]
     */
    protected $failedExceptions = [];

    /**
     * Get job results
     *
     * @return array
     */
    public function getJobResults()
    {
        return $this->results;
    }

    /**
     * Get job result
     *
     * @param  mixed $index
     * @return mixed
     */
    public function getJobResult($index)
    {
        return (isset($this->results[$index])) ? $this->results[$index] : null;
    }

    /**
     * Has job results
     *
     * @return boolean
     */
    public function hasJobResults()
    {
        return !empty($this->results);
    }

    /**
     * Get completed jobs
     *
     * @return array
     */
    public function getCompletedJobs()
    {
        return $this->completed;
    }

    /**
     * Get completed job
     *
     * @param  mixed $index
     * @return AbstractJob
     */
    public function getCompletedJob($index)
    {
        return (isset($this->completed[$index])) ? $this->completed[$index] : null;
    }

    /**
     * Has completed jobs
     *
     * @return boolean
     */
    public function hasCompletedJobs()
    {
        return !empty($this->completed);
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
     * @return \Exception
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
     * @param  Queue $queue
     * @return void
     */
    abstract public function processNext(Queue $queue = null);

}
