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

use Pop\Queue\Processor\Jobs\AbstractJob;
use Pop\Queue\Queue;

/**
 * Queue adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
interface AdapterInterface
{

    /**
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasJob($jobId);

    /**
     * Get job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @return array
     */
    public function getJob($jobId);

    /**
     * Update job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  mixed $job
     * @param  mixed $completed
     * @param  mixed $increment
     * @return void
     */
    public function updateJob($jobId, $job, $completed = false, $increment = false);

    /**
     * Check if queue adapter has jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasJobs($queue);

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getJobs($queue);

    /**
     * Check if queue adapter has completed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasCompletedJobs($queue);

    /**
     * Get queue completed jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getCompletedJobs($queue);

    /**
     * Check if queue stack has failed job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasFailedJob($jobId);

    /**
     * Get failed job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @return array
     */
    public function getFailedJob($jobId);

    /**
     * Update failed job from queue stack by job ID
     *
     * @param  mixed      $jobId
     * @param  mixed      $failedJob
     * @param  mixed      $failed
     * @param  \Exception $exception
     * @return void
     */
    public function updateFailedJob($jobId, $failedJob, $failed = false, \Exception $exception = null);

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasFailedJobs($queue);

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getFailedJobs($queue);

    /**
     * Push job onto queue stack
     *
     * @param  mixed $queue
     * @param  mixed $job
     * @param  mixed $priority
     * @return void
     */
    public function push($queue, $job, $priority = null);

    /**
     * Move failed job to failed queue stack
     *
     * @param  mixed      $queue
     * @param  mixed      $failedJob
     * @param  \Exception $exception
     * @return void
     */
    public function failed($queue, $failedJob, \Exception $exception = null);

    /**
     * Pop job off of queue stack
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop($jobId);

    /**
     * Clear completed jobs off of the queue stack
     *
     * @param  mixed   $queue
     * @param  boolean $all
     * @return void
     */
    public function clear($queue, $all = false);

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  boolean $all
     * @return void
     */
    public function flush($all = false);

}
