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
namespace Pop\Queue\Adapter;

use Pheanstalk\Connection;
use Pheanstalk\Pheanstalk;
use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs;

/**
 * Redis queue adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Beanstalk extends AbstractAdapter
{

    /**
     * Pheanstalk object
     * @var Pheanstalk
     */
    protected $pheanstalk = null;

    /**
     * Constructor
     *
     * Instantiate the beanstalk queue object
     *
     * @param  string $host
     * @param  int    $port
     * @param  int    $timeout
     */
    public function __construct($host = 'localhost', $port = null, $timeout = null)
    {
        $port    = $port ?? Pheanstalk::DEFAULT_PORT;
        $timeout = $timeout ?? Connection::DEFAULT_CONNECT_TIMEOUT;

        $this->pheanstalk = Pheanstalk::create($host, $port, $timeout);
    }

    /**
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasJob($jobId)
    {

    }

    /**
     * Get job from queue stack by job ID
     *
     * @param  mixed   $jobId
     * @param  boolean $unserialize
     * @return array
     */
    public function getJob($jobId, $unserialize = true)
    {

    }

    /**
     * Update job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  mixed $completed
     * @param  mixed $increment
     * @return void
     */
    public function updateJob($jobId, $completed = false, $increment = false)
    {

    }

    /**
     * Check if queue has jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasJobs($queue)
    {

    }

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getJobs($queue)
    {

    }

    /**
     * Check if queue has completed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasCompletedJobs($queue)
    {

    }

    /**
     * Get queue completed jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getCompletedJobs($queue)
    {

    }

    /**
     * Check if queue stack has failed job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasFailedJob($jobId)
    {

    }

    /**
     * Get failed job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @return array
     */
    public function getFailedJob($jobId)
    {

    }

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasFailedJobs($queue)
    {

    }

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getFailedJobs($queue)
    {

    }

    /**
     * Push job onto queue stack
     *
     * @param  mixed $queue
     * @param  mixed $job
     * @param  mixed $priority
     * @return void
     */
    public function push($queue, $job, $priority = null)
    {

    }

    /**
     * Move failed job to failed queue stack
     *
     * @param  mixed      $queue
     * @param  mixed      $jobId
     * @param  \Exception $exception
     * @return void
     */
    public function failed($queue, $jobId, \Exception $exception = null)
    {

    }

    /**
     * Pop job off of queue stack
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop($jobId)
    {

    }

    /**
     * Clear jobs off of the queue stack
     *
     * @param  mixed   $queue
     * @param  boolean $all
     * @return void
     */
    public function clear($queue, $all = false)
    {

    }

    /**
     * Clear failed jobs off of the queue stack
     *
     * @param  mixed $queue
     * @return void
     */
    public function clearFailed($queue)
    {

    }

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  boolean $all
     * @return void
     */
    public function flush($all = false)
    {

    }

    /**
     * Flush all failed jobs off of the queue stack
     *
     * @return void
     */
    public function flushFailed()
    {

    }

    /**
     * Get the pheanstalk object
     *
     * @return Pheanstalk
     */
    public function pheanstalk()
    {
        return $this->pheanstalk;
    }

}
