<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Adapter;

use Pop\Queue\Queue;
use Pop\Queue\Processor\AbstractJob;

/**
 * Redis queue adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Redis extends AbstractAdapter
{

    /**
     * Redis object
     * @var \Redis|null
     */
    protected \Redis|null $redis = null;

    /**
     * Job key prefix
     * @var string
     */
    protected string $prefix = 'pop-queue-';

    /**
     * Constructor
     *
     * Instantiate the redis queue object
     *
     * @param  string     $host
     * @param  int|string $port
     * @throws Exception|\RedisException
     */
    public function __construct(string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-queue-')
    {
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis = new \Redis();
        $this->prefix = $prefix;
        if (!$this->redis->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the redis server.');
        }
    }

    /**
     * Create file adapter
     *
     * @param  string     $host
     * @param  int|string $port
     * @throws Exception|\RedisException
     * @return Redis
     */
    public static function create(string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-queue-'): Redis
    {
        return new self($host, $port, $prefix);
    }

    /**
     * Get the redis object
     *
     * @return \Redis
     */
    public function redis(): \Redis
    {
        return $this->redis;
    }

    /**
     * Get the key prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Get all queues currently registered with this adapter
     *
     * @return array
     */
    public function getQueues(): array
    {
        $queueKeys = array_map(function($value) {
            return substr($value, 10);
        }, $this->redis->keys($this->prefix . '*'));

        return array_filter($queueKeys, function($value){
            return (!str_contains($value, '-payload') && !str_contains($value, '-completed') &&
                !str_contains($value, '-failed') && (strlen($value) != 40));
        });
    }

    /**
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasJob(mixed $jobId): bool
    {
        return ($this->redis->get($this->prefix . $jobId) !== false);
    }

    /**
     * Get job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getJob(mixed $jobId, bool $unserialize = true): array
    {
        $job = $this->redis->get($this->prefix . $jobId);
        if (!empty($job)) {
            $job        = unserialize($job);
            $jobPayload = $this->redis->get($this->prefix . $jobId . '-payload');
            if ($jobPayload !== false) {
                $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
            }
        } else {
            $job = [];
        }

        return $job;
    }

    /**
     * Save job in queue
     *
     * @param  string $queueName
     * @param  mixed $job
     * @param  array $jobData
     * @return string
     */
    public function saveJob(string $queueName, mixed $job, array $jobData) : string
    {
        $jobId              = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();
        $queueJobs          = $this->redis->get($this->prefix . $queueName);
        $queueJobsCompleted = $this->redis->get($this->prefix . $queueName . '-completed');
        $queueJobsFailed    = $this->redis->get($this->prefix . $queueName . '-failed');

        $queueJobs          = ($queueJobs !== false) ? unserialize($queueJobs) : [];
        $queueJobsCompleted = ($queueJobsCompleted !== false) ? unserialize($queueJobsCompleted) : [];
        $queueJobsFailed    = ($queueJobsFailed !== false) ? unserialize($queueJobsFailed) : [];

        if (!in_array($jobId, $queueJobs)) {
            $queueJobs[] = $jobId;
        }
        $this->redis->set($this->prefix . $queueName, serialize($queueJobs));
        $this->redis->set($this->prefix . $queueName . '-completed', serialize($queueJobsCompleted));
        $this->redis->set($this->prefix . $queueName . '-failed', serialize($queueJobsFailed));
        $this->redis->set($this->prefix . $jobId, serialize($jobData));
        $this->redis->set($this->prefix . $jobId . '-payload', serialize(clone $job));

        return $jobId;
    }

    /**
     * Update job from queue stack by job ID
     *
     * @param  AbstractJob $job
     * @return void
     */
    public function updateJob(AbstractJob $job): void
    {
        $jobId     = $job->getJobId();
        $jobData   = $this->getJob($jobId);
        $completed = $job->getCompleted();

        if (!empty($jobData)) {
            if (isset($jobData['attempts'])) {
                $jobData['attempts'] = $job->getAttempts();
            }

            if (isset($jobData['payload'])) {
                $jobData['payload'] = $job;
            }
            if (!empty($completed)) {
                $jobData['completed'] = date('Y-m-d H:i:s', $completed);
                $queueJobs            = $this->redis->get($this->prefix . $jobData['queue']);
                $queueJobs            = ($queueJobs !== false) ? unserialize($queueJobs) : [];
                $queueCompletedJobs   = $this->redis->get($this->prefix . $jobData['queue'] . '-completed');
                $queueCompletedJobs   = ($queueCompletedJobs !== false) ? unserialize($queueCompletedJobs) : [];

                if ((!$job->isValid()) && in_array($jobId, $queueJobs)) {
                    unset($queueJobs[array_search($jobId, $queueJobs)]);
                    $queueJobs = array_values($queueJobs);
                }
                if (!in_array($jobId, $queueCompletedJobs)) {
                    $queueCompletedJobs[] = $jobId;
                }

                $this->redis->set($this->prefix . $jobData['queue'], serialize($queueJobs));
                $this->redis->set($this->prefix . $jobData['queue'] . '-completed', serialize($queueCompletedJobs));
                $this->redis->set($this->prefix . $jobId . '-payload', serialize(clone $job));
            }

            $this->redis->set($this->prefix . $jobId, serialize($jobData));
        }
    }

    /**
     * Check if queue has jobs
     *
     * @param  mixed $queue
     * @return bool
     */
    public function hasJobs(mixed $queue): bool
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueJobs = $this->redis->get($this->prefix . $queueName);

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
     * @param  mixed $queue
     * @param  bool  $unserialize
     * @return array
     */
    public function getJobs(mixed $queue, bool $unserialize = true): array
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueJobs = $this->redis->get($this->prefix . $queueName);
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
     * @return bool
     */
    public function hasCompletedJob(mixed $jobId): bool
    {
        $job = $this->getJob($jobId, false);

        if (!empty($job)) {
            $queueCompletedJobs = $this->redis->get($this->prefix . $job['queue'] . '-completed');
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
     * @return bool
     */
    public function hasCompletedJobs(mixed $queue): bool
    {
        $queueName          = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueCompletedJobs = $this->redis->get($this->prefix . $queueName . '-completed');

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
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJob(mixed $jobId, bool $unserialize = true): array
    {
        if ($this->hasCompletedJob($jobId)) {
            return $this->getJob($jobId, $unserialize);
        } else {
            return [];
        }
    }

    /**
     * Get queue completed jobs
     *
     * @param  mixed $queue
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJobs(mixed $queue, bool $unserialize = true): array
    {
        $queueName          = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueCompletedJobs = $this->redis->get($this->prefix . $queueName . '-completed');
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
     * @return bool
     */
    public function hasFailedJob(mixed $jobId): bool
    {
        return ($this->redis->get($this->prefix . $jobId . '-failed') !== false);
    }

    /**
     * Get failed job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJob(mixed $jobId, bool $unserialize = true): array
    {
        $job = $this->redis->get($this->prefix . $jobId . '-failed');
        if (!empty($job)) {
            $job        = unserialize($job);
            $jobPayload = $this->redis->get($this->prefix . $jobId . '-payload');
            if ($jobPayload !== false) {
                $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
            }
        } else {
            $job = [];
        }

        return $job;
    }

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  mixed $queue
     * @return bool
     */
    public function hasFailedJobs(mixed $queue): bool
    {
        $queueName       = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueFailedJobs = $this->redis->get($this->prefix . $queueName . '-failed');

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
     * @param  mixed $queue
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJobs(mixed $queue, bool $unserialize = true): array
    {
        $queueName       = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueFailedJobs = $this->redis->get($this->prefix . $queueName . '-failed');
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
    public function push(mixed $queue, mixed $job, mixed $priority = null): string
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $jobId     = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();
        $jobData   = [
            'job_id'       => $jobId,
            'queue'        => $queueName,
            'priority'     => $priority,
            'max_attempts' => $job->getMaxAttempts(),
            'attempts'     => 0,
            'completed'    => null
        ];

        return $this->saveJob($queueName, $job, $jobData);
    }

    /**
     * Move failed job to failed queue stack
     *
     * @param  mixed           $queue
     * @param  mixed           $failedJob
     * @param  \Exception|null $exception
     * @return void
     */
    public function failed(mixed $queue, mixed $failedJob, \Exception|null $exception = null): void
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $failedJobData = [
            'job_id'    => $failedJob,
            'queue'     => $queueName,
            'exception' => ($exception !== null) ? $exception->getMessage() : null,
            'failed'    => date('Y-m-d H:i:s')
        ];

        $queueJobsFailed = $this->redis->get($this->prefix . $queueName . '-failed');
        $queueJobsFailed = ($queueJobsFailed !== false) ? unserialize($queueJobsFailed) : [];

        if (!in_array($failedJob, $queueJobsFailed)) {
            $queueJobsFailed[] = $failedJob;
        }

        $this->redis->set($this->prefix . $failedJob . '-failed', serialize($failedJobData));
        $this->redis->set($this->prefix . $queueName . '-failed', serialize($queueJobsFailed));

        if (!empty($failedJob)) {
            $this->pop($failedJob);
        }
    }

    /**
     * Pop job off of queue stack
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop(mixed $jobId): void
    {
        $jobData = $this->getJob($jobId);

        if (!empty($jobData)) {
            $queueJobs = $this->redis->get($this->prefix . $jobData['queue']);
            $queueJobs = ($queueJobs !== false) ? unserialize($queueJobs) : [];

            if (in_array($jobId, $queueJobs)) {
                unset($queueJobs[array_search($jobId, $queueJobs)]);
                $queueJobs = array_values($queueJobs);
            }

            $this->redis->set($this->prefix . $jobData['queue'], serialize($queueJobs));
        }

        $this->redis->del($this->prefix . $jobId);
    }

    /**
     * Clear jobs off of the queue stack
     *
     * @param  mixed $queue
     * @param  bool  $all
     * @return void
     */
    public function clear(mixed $queue, bool $all = false): void
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueJobs = $this->redis->get($this->prefix . $queueName);

        if ($queueJobs !== false) {
            $queueJobs = unserialize($queueJobs);
            foreach ($queueJobs as $jobId) {
                $this->redis->del($this->prefix . $jobId);
            }
        }

        if ($all) {
            $this->redis->del($this->prefix . $queueName . '-completed');
        }
    }

    /**
     * Clear failed jobs off of the queue stack
     *
     * @param  mixed $queue
     * @return void
     */
    public function clearFailed(mixed $queue): void
    {
        $queueName       = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $queueFailedJobs = $this->redis->get($this->prefix . $queueName . '-failed');

        if ($queueFailedJobs !== false) {
            $queueFailedJobs = unserialize($queueFailedJobs);
            foreach ($queueFailedJobs as $failedJobId) {
                $this->redis->del($this->prefix . $failedJobId . '-failed');
            }
        }

        $this->redis->del($this->prefix . $queueName . '-failed');
    }

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  bool $all
     * @return void
     */
    public function flush(bool $all = false): void
    {
        $keys = $this->redis->keys($this->prefix . '*');
        $keys = array_filter($keys, function($value) {
            return (!str_contains($value, 'failed'));
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
    public function flushFailed(): void
    {
        $keys = $this->redis->keys($this->prefix . '*');
        $keys = array_filter($keys, function($value) {
            return (str_contains($value, 'failed'));
        });
        $this->redis->del($keys);
    }

    /**
     * Flush all pop queue items
     *
     * @return void
     */
    public function flushAll(): void
    {
        $this->redis->del($this->redis->keys($this->prefix . '*'));
    }

}
