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

use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs;

/**
 * File queue adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class File extends AbstractAdapter
{

    /**
     * Queue folder
     * @var string
     */
    protected $folder = null;

    /**
     * Constructor
     *
     * Instantiate the file queue object
     *
     * @param string $folder
     */
    public function __construct($folder)
    {
        if (!file_exists($folder)) {
            throw new Exception("Error: The queue folder '" . $folder . "' does not exist.");
        }
        if (!is_writable($folder)) {
            throw new Exception("Error: The queue folder '" . $folder . "' is not writable.");
        }

        $this->folder = $folder;
        $this->initFolders();
    }

    /**
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasJob($jobId)
    {
        return (file_exists($this->folder . '/' . $this->queueName . '/' . $jobId));
    }

    /**
     * Get job from queue stack by job ID
     *
     * @param  mixed   $jobId
     * @param  boolean $unserialize
     * @return array|boolean
     */
    public function getJob($jobId, $unserialize = true)
    {
        if (file_exists($this->folder . '/' . $jobId)) {
            $job = unserialize(file_get_contents($this->folder . '/' . $jobId));
            if (file_exists($this->folder . '/payloads/' . $jobId)) {
                $jobPayload = file_get_contents($this->folder . '/payloads/' . $jobId);
                if ($unserialize) {
                    $jobPayload = unserialize($jobPayload);
                }
                $job['payload'] = $jobPayload;
            }
            return $job;
        } else {
            return false;
        }
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
        return (count($this->getFiles($this->folder . '/' . $this->queueName)) > 0);

    }

    /**
     * Get queue jobs
     *
     * @param  mixed   $queue
     * @param  boolean $unserialize
     * @return array
     */
    public function getJobs($queue, $unserialize = true)
    {

    }

    /**
     * Check if queue stack has completed job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasCompletedJob($jobId)
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
     * Get queue completed job
     *
     * @param  mixed   $jobId
     * @param  boolean $unserialize
     * @return array
     */
    public function getCompletedJob($jobId, $unserialize = true)
    {

    }

    /**
     * Get queue completed jobs
     *
     * @param  mixed   $queue
     * @param  boolean $unserialize
     * @return array
     */
    public function getCompletedJobs($queue, $unserialize = true)
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
     * @param  mixed   $jobId
     * @param  boolean $unserialize
     * @return array
     */
    public function getFailedJob($jobId, $unserialize = true)
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
     * @param  mixed   $queue
     * @param  boolean $unserialize
     * @return array
     */
    public function getFailedJobs($queue, $unserialize = true)
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
     * Flush all pop queue items
     *
     * @return void
     */
    public function flushAll()
    {

    }

    /**
     * Get the queue folder
     *
     * @return string
     */
    public function folder()
    {
        return $this->folder;
    }



    /**
     * Initialize queue folders
     *
     * @return File
     */
    public function initFolders()
    {
        if (!file_exists($this->folder . '/payloads')) {
            mkdir($this->folder . '/payloads');
            chmod($this->folder . '/payloads', 0777);
        }
        if (!file_exists($this->folder . '/completed')) {
            mkdir($this->folder . '/completed');
            chmod($this->folder . '/completed', 0777);
        }
        if (!file_exists($this->folder . '/failed')) {
            mkdir($this->folder . '/failed');
            chmod($this->folder . '/failed', 0777);
        }

        return $this;
    }

    /**
     * Get files from folder
     *
     * @param  string $folder
     * @return array
     */
    public function getFiles($folder)
    {
        return array_values(array_filter(scandir($folder), function($value){
            return (($value != '.') && ($value != '..') &&
                ($value != 'payloads') && ($value != 'completed') && ($value != 'failed'));
        }));
    }

}
