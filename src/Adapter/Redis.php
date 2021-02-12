<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Adapter;

use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs;

/**
 * Redis queue adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
 */
class Redis extends AbstractAdapter
{

    /**
     * Redis object
     * @var \Redis
     */
    protected $redis = null;

    /**
     * Constructor
     *
     * Instantiate the redis queue object
     *
     * @param  string $host
     * @param  int    $port
     * @throws Exception
     */
    public function __construct($host = 'localhost', $port = 6379)
    {
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis = new \Redis();
        if (!$this->redis->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the redis server.');
        }
    }

    /**
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasJob($jobId)
    {
        return ($this->redis->get('pop-queue-' . $jobId) !== false);
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
        $job = $this->redis->get('pop-queue-' . $jobId);
        if ($job !== false) {
            $job        = unserialize($job);
            $jobPayload = $this->redis->get('pop-queue-' . $jobId . '-payload');
            if ($jobPayload !== false) {
                $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
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
            if ($completed !== false) {
                $jobData['completed'] = ($completed === true) ? date('Y-m-d H:i:s') : $completed;
                $queueJobs            = $this->redis->get('pop-queue-' . $jobData['queue']);
                $queueJobs            = ($queueJobs !== false) ? unserialize($queueJobs) : [];
                $queueCompletedJobs   = $this->redis->get('pop-queue-' . $jobData['queue'] . '-completed');
                $queueCompletedJobs   = ($queueCompletedJobs !== false) ? unserialize($queueCompletedJobs) : [];

                if (in_array($jobId, $queueJobs)) {
                    unset($queueJobs[array_search($jobId, $queueJobs)]);
                    $queueJobs = array_values($queueJobs);
                }
                if (!in_array($jobId, $queueCompletedJobs)) {
                    $queueCompletedJobs[] = $jobId;
                }

                $this->redis->set('pop-queue-' . $jobData['queue'], serialize($queueJobs));
                $this->redis->set('pop-queue-' . $jobData['queue'] . '-completed', serialize($queueCompletedJobs));
            }
            if ($increment !== false) {
                if (($increment === true) && isset($jobData['attempts'])) {
                    $jobData['attempts']++;
                } else {
                    $jobData['attempts'] = (int)$increment;
                }
            }

            if (isset($jobData['payload'])) {
                unset($jobData['payload']);
            }

            $this->redis->set('pop-queue-' . $jobId, serialize($jobData));
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
        $queueJobs = $this->redis->get('pop-queue-' . $queueName);

        if ($queueJobs !== false) {
            $queueJobs = unserialize($queueJobs);
            if (!empty($queueJobs)) {
                foreach ($queueJobs as $jobId) {
                    if ($this->hasJob($jobId)) {
                        return true;
                    }
                }
            }
            return false;
        } else {
            return false;
        }
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
        $queueJobs = $this->redis->get('pop-queue-' . $queueName);
        $jobs      = [];

        if ($queueJobs !== false) {
            $queueJobs = unserialize($queueJobs);
            foreach ($queueJobs as $jobId) {
                $jobs[$jobId] = $this->getJob($jobId, $unserialize);
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
        $job = $this->getJob($jobId, false);

        if ($job !== false) {
            $queueCompletedJobs = $this->redis->get('pop-queue-' . $job['queue'] . '-completed');
            if ($queueCompletedJobs !== false) {
                $queueCompletedJobs = unserialize($queueCompletedJobs);
                return (in_array($jobId, $queueCompletedJobs) && !empty($job['completed']));
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Check if queue has completed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasCompletedJobs($queue)
    {
        $queueName          = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueCompletedJobs = $this->redis->get('pop-queue-' . $queueName . '-completed');

        if ($queueCompletedJobs !== false) {
            $queueCompletedJobs = unserialize($queueCompletedJobs);
            return (count($queueCompletedJobs) > 0);
        } else {
            return false;
        }
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
        if ($this->hasCompletedJob($jobId)) {
            return $this->getJob($jobId, $unserialize);
        } else {
            return null;
        }
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
        $queueCompletedJobs = $this->redis->get('pop-queue-' . $queueName . '-completed');
        $completedJobs      = [];

        if ($queueCompletedJobs !== false) {
            $queueCompletedJobs = unserialize($queueCompletedJobs);
            foreach ($queueCompletedJobs as $jobId) {
                $completedJobs[$jobId] = $this->getJob($jobId, $unserialize);
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
        return ($this->redis->get('pop-queue-' . $jobId . '-failed') !== false);
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
        $job = $this->redis->get('pop-queue-' . $jobId . '-failed');
        if ($job !== false) {
            $job        = unserialize($job);
            $jobPayload = $this->redis->get('pop-queue-' . $jobId . '-payload');
            if ($jobPayload !== false) {
                $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
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
        $queueName       = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueFailedJobs = $this->redis->get('pop-queue-' . $queueName . '-failed');

        if ($queueFailedJobs !== false) {
            $queueFailedJobs = unserialize($queueFailedJobs);
            return (count($queueFailedJobs) > 0);
        } else {
            return false;
        }
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
        $queueFailedJobs = $this->redis->get('pop-queue-' . $queueName . '-failed');
        $failedJobs      = [];

        if ($queueFailedJobs !== false) {
            $queueFailedJobs = unserialize($queueFailedJobs);
            foreach ($queueFailedJobs as $jobId) {
                $failedJobs[$jobId] = $this->getFailedJob($jobId, $unserialize);
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

        $queueJobs          = $this->redis->get('pop-queue-' . $queueName);
        $queueJobsCompleted = $this->redis->get('pop-queue-' . $queueName . '-completed');
        $queueJobsFailed    = $this->redis->get('pop-queue-' . $queueName . '-failed');

        $queueJobs          = ($queueJobs !== false) ? unserialize($queueJobs) : [];
        $queueJobsCompleted = ($queueJobsCompleted !== false) ? unserialize($queueJobsCompleted) : [];
        $queueJobsFailed    = ($queueJobsFailed !== false) ? unserialize($queueJobsFailed) : [];

        if (!in_array($jobId, $queueJobs)) {
            $queueJobs[] = $jobId;
        }

        $jobData = [
            'job_id'    => $jobId,
            'queue'     => $queueName,
            'priority'  => $priority,
            'attempts'  => 0,
            'completed' => null
        ];

        $this->redis->set('pop-queue-' . $queueName, serialize($queueJobs));
        $this->redis->set('pop-queue-' . $queueName . '-completed', serialize($queueJobsCompleted));
        $this->redis->set('pop-queue-' . $queueName . '-failed', serialize($queueJobsFailed));
        $this->redis->set('pop-queue-' . $jobId, serialize($jobData));
        $this->redis->set('pop-queue-' . $jobId . '-payload', serialize(clone $job));

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

        $failedJobData = [
            'job_id'    => $jobId,
            'queue'     => $queueName,
            'exception' => (null !== $exception) ? $exception->getMessage() : null,
            'failed'    => date('Y-m-d H:i:s')
        ];

        $queueJobsFailed = $this->redis->get('pop-queue-' . $queueName . '-failed');
        $queueJobsFailed = ($queueJobsFailed !== false) ? unserialize($queueJobsFailed) : [];

        if (!in_array($jobId, $queueJobsFailed)) {
            $queueJobsFailed[] = $jobId;
        }

        $this->redis->set('pop-queue-' . $jobId . '-failed', serialize($failedJobData));
        $this->redis->set('pop-queue-' . $queueName . '-failed', serialize($queueJobsFailed));

        if (!empty($jobId)) {
            $this->pop($jobId);
        }
    }

    /**
     * Pop job off of queue stack
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop($jobId)
    {
        $jobData = $this->getJob($jobId);

        if ($jobData !== false) {
            $queueJobs = $this->redis->get('pop-queue-' . $jobData['queue']);
            $queueJobs = ($queueJobs !== false) ? unserialize($queueJobs) : [];

            if (in_array($jobId, $queueJobs)) {
                unset($queueJobs[array_search($jobId, $queueJobs)]);
                $queueJobs = array_values($queueJobs);
            }

            $this->redis->set('pop-queue-' . $jobData['queue'], serialize($queueJobs));
        }

        $this->redis->del('pop-queue-' . $jobId);
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
        $queueJobs = $this->redis->get('pop-queue-' . $queueName);

        if ($queueJobs !== false) {
            $queueJobs = unserialize($queueJobs);
            foreach ($queueJobs as $jobId) {
                $this->redis->del('pop-queue-' . $jobId);
            }
        }

        if ($all) {
            $this->redis->del('pop-queue-' . $queueName . '-completed');
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
        $queueName       = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueFailedJobs = $this->redis->get('pop-queue-' . $queueName . '-failed');

        if ($queueFailedJobs !== false) {
            $queueFailedJobs = unserialize($queueFailedJobs);
            foreach ($queueFailedJobs as $failedJobId) {
                $this->redis->del('pop-queue-' . $failedJobId . '-failed');
            }
        }

        $this->redis->del('pop-queue-' . $queueName . '-failed');
    }

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  boolean $all
     * @return void
     */
    public function flush($all = false)
    {
        $keys = $this->redis->keys('pop-queue-*');
        $keys = array_filter($keys, function($value) {
            return (strpos($value, 'failed') === false);
        });
        if ($all) {
            $this->redis->del($keys);
        }
    }

    /**
     * Flush all failed jobs off of the queue stack
     *
     * @return void
     */
    public function flushFailed()
    {
        $keys = $this->redis->keys('pop-queue-*');
        $keys = array_filter($keys, function($value) {
            return (strpos($value, 'failed') !== false);
        });
        $this->redis->del($keys);
    }

    /**
     * Flush all pop queue items
     *
     * @return void
     */
    public function flushAll()
    {
        $this->redis->del($this->redis->keys('pop-queue-*'));
    }

    /**
     * Get the redis object.
     *
     * @return \Redis
     */
    public function redis()
    {
        return $this->redis;
    }

}
