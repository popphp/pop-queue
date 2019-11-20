<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.0.0
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
    }

    /**
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasJob($jobId)
    {
        $queueFolders = $this->getFiles($this->folder);
        $hasJob       = false;

        foreach ($queueFolders as $queueFolder) {
            if (file_exists($this->folder . '/' . $queueFolder . '/' . $jobId)) {
                $hasJob = true;
                break;
            }
        }

        return $hasJob;
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
        $queueFolders = $this->getFiles($this->folder);
        $job          = false;

        foreach ($queueFolders as $queueFolder) {
            if (file_exists($this->folder . '/' . $queueFolder . '/' . $jobId)) {
                $job = unserialize(file_get_contents($this->folder . '/' . $queueFolder . '/' . $jobId));
                if (file_exists($this->folder . '/' . $queueFolder . '/' . $jobId . '-payload')) {
                    $jobPayload = file_get_contents($this->folder . '/' . $queueFolder . '/' . $jobId . '-payload');
                    if ($jobPayload !== false) {
                        $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
                    }
                }
                break;
            }
        }

        return $job;
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
        $jobData = $this->getJob($jobId);

        if ($jobData !== false) {
            $queueName = $jobData['queue'];
            if (isset($jobData['payload'])) {
                unset($jobData['payload']);
            }
            if ($increment !== false) {
                if (($increment === true) && isset($jobData['attempts'])) {
                    $jobData['attempts']++;
                } else {
                    $jobData['attempts'] = (int)$increment;
                }
            }
            if ($completed !== false) {
                $jobData['completed'] = ($completed === true) ? date('Y-m-d H:i:s') : $completed;

                file_put_contents($this->folder . '/' . $queueName . '/completed/' . $jobId, serialize($jobData));
                if (file_exists($this->folder . '/' . $queueName . '/' . $jobId)) {
                    unlink($this->folder . '/' . $queueName . '/' . $jobId);
                }
            } else {
                file_put_contents($this->folder . '/' . $queueName . '/' . $jobId, serialize($jobData));
            }
        }
    }

    /**
     * Check if queue has jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        return (count($this->getFiles($this->folder . '/' . $queueName)) > 0);
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
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueJobs = $this->getFiles($this->folder . '/' . $queueName);
        $jobs      = [];

        if (count($queueJobs) > 0) {
            foreach ($queueJobs as $jobId) {
                if ((strpos($jobId, '-payload') === false) && file_exists($this->folder . '/' . $queueName . '/' . $jobId)) {
                    $job = unserialize(file_get_contents($this->folder . '/' . $queueName . '/' . $jobId));
                    if (file_exists($this->folder . '/' . $queueName . '/' . $jobId . '-payload')) {
                        $jobPayload = file_get_contents($this->folder . '/' . $queueName . '/' . $jobId . '-payload');
                        if ($unserialize) {
                            $jobPayload = unserialize($jobPayload);
                        }

                        $job['payload'] = $jobPayload;
                    }

                    $jobs[$jobId] = $job;
                }
            }
        }

        return $jobs;
    }

    /**
     * Check if queue stack has completed job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasCompletedJob($jobId)
    {
        $queueFolders = $this->getFiles($this->folder);
        $hasJob       = false;

        foreach ($queueFolders as $queueFolder) {
            if (file_exists($this->folder . '/' . $queueFolder . '/completed/' . $jobId)) {
                $hasJob = true;
                break;
            }
        }

        return $hasJob;
    }

    /**
     * Check if queue has completed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasCompletedJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        return (count($this->getFiles($this->folder . '/' . $queueName . '/completed')) > 0);
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
        $queueFolders = $this->getFiles($this->folder);
        $job          = false;

        foreach ($queueFolders as $queueFolder) {
            if (file_exists($this->folder . '/' . $queueFolder . '/completed/' . $jobId)) {
                $job = unserialize(file_get_contents($this->folder . '/' . $queueFolder . '/completed/' . $jobId));
                if (file_exists($this->folder . '/' . $queueFolder . '/' . $jobId . '-payload')) {
                    $jobPayload = file_get_contents($this->folder . '/' . $queueFolder . '/' . $jobId . '-payload');
                    if ($jobPayload !== false) {
                        $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
                    }
                }
                break;
            }
        }

        return $job;
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
        $queueName          = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueCompletedJobs = $this->getFiles($this->folder . '/' . $queueName . '/completed');
        $completedJobs      = [];

        if (count($queueCompletedJobs) > 0) {
            foreach ($queueCompletedJobs as $jobId) {
                if (file_exists($this->folder . '/' . $queueName . '/completed/' . $jobId)) {
                    $completedJob = unserialize(file_get_contents($this->folder . '/' . $queueName . '/completed/' . $jobId));
                    if (file_exists($this->folder . '/' . $queueName . '/' . $jobId . '-payload')) {
                        $jobPayload = file_get_contents($this->folder . '/' . $queueName . '/' . $jobId . '-payload');
                        if ($unserialize) {
                            $jobPayload = unserialize($jobPayload);
                        }

                        $completedJob['payload'] = $jobPayload;
                    }

                    $completedJobs[$jobId] = $completedJob;
                }
            }
        }

        return $completedJobs;
    }

    /**
     * Check if queue stack has failed job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasFailedJob($jobId)
    {
        $queueFolders = $this->getFiles($this->folder);
        $hasJob       = false;

        foreach ($queueFolders as $queueFolder) {
            if (file_exists($this->folder . '/' . $queueFolder . '/failed/' . $jobId)) {
                $hasJob = true;
                break;
            }
        }

        return $hasJob;
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
        $queueFolders = $this->getFiles($this->folder);
        $job          = false;

        foreach ($queueFolders as $queueFolder) {
            if (file_exists($this->folder . '/' . $queueFolder . '/failed/' . $jobId)) {
                $job = unserialize(file_get_contents($this->folder . '/' . $queueFolder . '/failed/' . $jobId));
                if (file_exists($this->folder . '/' . $queueFolder . '/' . $jobId . '-payload')) {
                    $jobPayload = file_get_contents($this->folder . '/' . $queueFolder . '/' . $jobId . '-payload');
                    if ($jobPayload !== false) {
                        $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
                    }
                }
                break;
            }
        }

        return $job;
    }

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasFailedJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        return (count($this->getFiles($this->folder . '/' . $queueName . '/failed')) > 0);
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
        $queueName       = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueFailedJobs = $this->getFiles($this->folder . '/' . $queueName . '/failed');
        $failedJobs      = [];

        if (count($queueFailedJobs) > 0) {
            foreach ($queueFailedJobs as $jobId) {
                if (file_exists($this->folder . '/' . $queueName . '/failed/' . $jobId)) {
                    $failedJob = unserialize(file_get_contents($this->folder . '/' . $queueName . '/failed/' . $jobId));
                    if (file_exists($this->folder . '/' . $queueName . '/' . $jobId . '-payload')) {
                        $jobPayload = file_get_contents($this->folder . '/' . $queueName . '/' . $jobId . '-payload');
                        if ($unserialize) {
                            $jobPayload = unserialize($jobPayload);
                        }

                        $failedJob['payload'] = $jobPayload;
                    }

                    $failedJobs[$jobId] = $failedJob;
                }
            }
        }

        return $failedJobs;
    }

    /**
     * Push job onto queue stack
     *
     * @param  mixed $queue
     * @param  mixed $job
     * @param  mixed $priority
     * @return string
     */
    public function push($queue, $job, $priority = null)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $jobId     = null;

        if ($job instanceof Jobs\Schedule) {
            $jobId = ($job->getJob()->hasJobId()) ? $job->getJob()->getJobId() :$job->getJob()->generateJobId();
        } else if ($job instanceof Jobs\Job) {
            $jobId = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();
        }

        $this->initFolders($queueName);

        $jobData = [
            'job_id'    => $jobId,
            'queue'     => $queueName,
            'priority'  => $priority,
            'attempts'  => 0,
            'completed' => null
        ];

        file_put_contents($this->folder . '/' . $queueName . '/' . $jobId, serialize($jobData));
        file_put_contents($this->folder . '/' . $queueName . '/' . $jobId . '-payload', serialize(clone $job));

        return $jobId;
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
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $this->initFolders($queueName);

        $failedJobData = [
            'job_id'    => $jobId,
            'queue'     => $queueName,
            'exception' => (null !== $exception) ? $exception->getMessage() : null,
            'failed'    => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->folder . '/' . $queueName . '/failed/' . $jobId, serialize($failedJobData));

        if (!empty($jobId)) {
            $this->pop($jobId, false);
        }
    }

    /**
     * Pop job off of queue stack
     *
     * @param  mixed   $jobId
     * @param  boolean $payload
     * @return void
     */
    public function pop($jobId, $payload = true)
    {
        $jobData   = $this->getJob($jobId);
        $queueName = $jobData['queue'];

        if (file_exists($this->folder . '/' . $queueName . '/' . $jobId)) {
            unlink($this->folder . '/' . $queueName . '/' . $jobId);
        }
        if (($payload) && file_exists($this->folder . '/' . $queueName . '/' . $jobId . '-payload')) {
            unlink($this->folder . '/' . $queueName . '/' . $jobId . '-payload');
        }
        if (file_exists($this->folder . '/' . $queueName . '/completed/' . $jobId)) {
            unlink($this->folder . '/' . $queueName . '/completed/' . $jobId);
        }
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
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        if (file_exists($this->folder . '/' . $queueName)) {
            $this->clearFolder($this->folder . '/' . $queueName);
        }
        if (($all) && file_exists($this->folder . '/' . $queueName . '/completed')) {
            $this->clearFolder($this->folder . '/' . $queueName . '/completed');
        }

        if (is_dir($this->folder . '/' . $queueName) && count(scandir($this->folder . '/' . $queueName)) == 2) {
            rmdir($this->folder . '/' . $queueName);
        }
    }

    /**
     * Clear failed jobs off of the queue stack
     *
     * @param  mixed $queue
     * @return void
     */
    public function clearFailed($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        if (file_exists($this->folder . '/' . $queueName . '/failed')) {
            $this->clearFolder($this->folder . '/' . $queueName . '/failed');
        }

        if (is_dir($this->folder . '/' . $queueName) && count(scandir($this->folder . '/' . $queueName)) == 2) {
            rmdir($this->folder . '/' . $queueName);
        }
    }

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  boolean $all
     * @return void
     */
    public function flush($all = false)
    {
        $queueFolders = $this->getFiles($this->folder);

        foreach ($queueFolders as $queueFolder) {
            $this->clear($queueFolder, $all);
        }
    }

    /**
     * Flush all failed jobs off of the queue stack
     *
     * @return void
     */
    public function flushFailed()
    {
        $queueFolders = $this->getFiles($this->folder);

        foreach ($queueFolders as $queueFolder) {
            $this->clearFailed($queueFolder);
        }
    }

    /**
     * Flush all pop queue items
     *
     * @return void
     */
    public function flushAll()
    {
        $this->flushFailed();
        $this->flush(true);

        $queueFolders = $this->getFiles($this->folder);

        foreach ($queueFolders as $queueFolder) {
            if (is_dir($this->folder . '/' . $queueFolder) && count(scandir($this->folder . '/' . $queueFolder)) == 2) {
                rmdir($this->folder . '/' . $queueFolder);
            }
        }
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
     * @param  string $queueName
     * @return File
     */
    public function initFolders($queueName)
    {
        if (!file_exists($this->folder . '/' . $queueName)) {
            mkdir($this->folder . '/' . $queueName, 0777);
        }
        if (!file_exists($this->folder . '/' . $queueName . '/completed')) {
            mkdir($this->folder . '/' . $queueName . '/completed', 0777);
        }
        if (!file_exists($this->folder . '/' . $queueName . '/failed')) {
            mkdir($this->folder . '/' . $queueName . '/failed', 0777);
        }

        return $this;
    }

    /**
     * Clear queue folder
     *
     * @param  string  $folder
     * @throws Exception
     * @return File
     */
    public function clearFolder($folder)
    {
        $files = $this->getFiles($folder);

        foreach ($files as $file) {
            if (file_exists($folder . '/' . $file)) {
                unlink($folder . '/' . $file);
            }
        }

        return $this;
    }

    /**
     * Remove queue folder
     *
     * @param  string $queueName
     * @return File
     */
    public function removeQueueFolder($queueName)
    {
        $this->clearFolder($this->folder . '/' . $queueName . '/completed');
        $this->clearFolder($this->folder . '/' . $queueName . '/failed');
        $this->clearFolder($this->folder . '/' . $queueName);


        if (file_exists($this->folder . '/' . $queueName . '/completed')) {
            rmdir($this->folder . '/' . $queueName . '/completed');
        }
        if (file_exists($this->folder . '/' . $queueName . '/failed')) {
            rmdir($this->folder . '/' . $queueName . '/failed');
        }
        if (file_exists($this->folder . '/' . $queueName)) {
            rmdir($this->folder . '/' . $queueName);
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
        if (is_dir($folder)) {
            return array_values(array_filter(scandir($folder), function($value){
                return (($value != '.') && ($value != '..') && ($value != 'completed') && ($value != 'failed'));
            }));
        } else {
            return [];
        }
    }

}
