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

use Pop\Queue\Worker;
use Pop\Queue\Processor\AbstractJob;

/**
 * Redis worker adapter class
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
    protected string $prefix = 'pop-worker-';

    /**
     * Constructor
     *
     * Instantiate the redis worker object
     *
     * @param  string     $host
     * @param  int|string $port
     * @throws Exception|\RedisException
     */
    public function __construct(string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-worker-')
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
    public static function create(string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-worker-'): Redis
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
     * Get all workers currently registered with this adapter
     *
     * @return array
     */
    public function getWorkers(): array
    {
        $workerKeys = array_map(function($value) {
            return substr($value, strlen($this->prefix));
        }, $this->redis->keys($this->prefix . '*'));

        return array_filter($workerKeys, function($value){
            return (!str_contains($value, '-payload') && !str_contains($value, '-completed') &&
                !str_contains($value, '-failed') && (strlen($value) != 40));
        });
    }

    /**
     * Check if worker has job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasJob(mixed $jobId): bool
    {
        return ($this->redis->get($this->prefix . $jobId) !== false);
    }

    /**
     * Get job from worker by job ID
     *
     * @param  mixed $jobId
     * @param  bool $unserialize
     * @throws \RedisException
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
     * Save job in worker
     *
     * @param  string $workerName
     * @param  mixed $job
     * @param  array $jobData
     * @return string
     */
    public function saveJob(string $workerName, mixed $job, array $jobData) : string
    {
        $jobId               = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();
        $workerJobs          = $this->redis->get($this->prefix . $workerName);
        $workerJobsCompleted = $this->redis->get($this->prefix . $workerName . '-completed');
        $workerJobsFailed    = $this->redis->get($this->prefix . $workerName . '-failed');

        $workerJobs          = ($workerJobs !== false) ? unserialize($workerJobs) : [];
        $workerJobsCompleted = ($workerJobsCompleted !== false) ? unserialize($workerJobsCompleted) : [];
        $workerJobsFailed    = ($workerJobsFailed !== false) ? unserialize($workerJobsFailed) : [];

        if (!in_array($jobId, $workerJobs)) {
            $workerJobs[] = $jobId;
        }
        $this->redis->set($this->prefix . $workerName, serialize($workerJobs));
        $this->redis->set($this->prefix . $workerName . '-completed', serialize($workerJobsCompleted));
        $this->redis->set($this->prefix . $workerName . '-failed', serialize($workerJobsFailed));
        $this->redis->set($this->prefix . $jobId, serialize($jobData));
        $this->redis->set($this->prefix . $jobId . '-payload', serialize(clone $job));

        return $jobId;
    }

    /**
     * Update job from worker by job ID
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
                $jobData['completed']  = date('Y-m-d H:i:s', $completed);
                $workerJobs            = $this->redis->get($this->prefix . $jobData['worker']);
                $workerJobs            = ($workerJobs !== false) ? unserialize($workerJobs) : [];
                $workerCompletedJobs   = $this->redis->get($this->prefix . $jobData['worker'] . '-completed');
                $workerCompletedJobs   = ($workerCompletedJobs !== false) ? unserialize($workerCompletedJobs) : [];

                if ((!$job->isValid()) && in_array($jobId, $workerJobs)) {
                    unset($workerJobs[array_search($jobId, $workerJobs)]);
                    $workerJobs = array_values($workerJobs);
                }
                if (!in_array($jobId, $workerCompletedJobs)) {
                    $workerCompletedJobs[] = $jobId;
                }

                $this->redis->set($this->prefix . $jobData['worker'], serialize($workerJobs));
                $this->redis->set($this->prefix . $jobData['worker'] . '-completed', serialize($workerCompletedJobs));
                $this->redis->set($this->prefix . $jobId . '-payload', serialize(clone $job));
            }

            $this->redis->set($this->prefix . $jobId, serialize($jobData));
        }
    }

    /**
     * Check if worker has jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasJobs(mixed $worker): bool
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerJobs = $this->redis->get($this->prefix . $workerName);

        if ($workerJobs !== false) {
            $workerJobs = unserialize($workerJobs);
            if (!empty($workerJobs)) {
                foreach ($workerJobs as $jobId) {
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
     * Get worker jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getJobs(mixed $worker, bool $unserialize = true): array
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerJobs = $this->redis->get($this->prefix . $workerName);
        $jobs       = [];

        if ($workerJobs !== false) {
            $workerJobs = unserialize($workerJobs);
            foreach ($workerJobs as $jobId) {
                $jobs[$jobId] = $this->getJob($jobId, $unserialize);
            }
        }

        return $jobs;
    }

    /**
     * Check if worker has completed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasCompletedJob(mixed $jobId): bool
    {
        $job = $this->getJob($jobId, false);

        if (!empty($job)) {
            $workerCompletedJobs = $this->redis->get($this->prefix . $job['worker'] . '-completed');
            if ($workerCompletedJobs !== false) {
                $workerCompletedJobs = unserialize($workerCompletedJobs);
                return (in_array($jobId, $workerCompletedJobs) && !empty($job['completed']));
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Check if worker has completed jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasCompletedJobs(mixed $worker): bool
    {
        $workerName          = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerCompletedJobs = $this->redis->get($this->prefix . $workerName . '-completed');

        if ($workerCompletedJobs !== false) {
            $workerCompletedJobs = unserialize($workerCompletedJobs);
            return (count($workerCompletedJobs) > 0);
        } else {
            return false;
        }
    }

    /**
     * Get worker completed job
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
     * Get worker completed jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJobs(mixed $worker, bool $unserialize = true): array
    {
        $workerName          = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerCompletedJobs = $this->redis->get($this->prefix . $workerName . '-completed');
        $completedJobs       = [];

        if ($workerCompletedJobs !== false) {
            $workerCompletedJobs = unserialize($workerCompletedJobs);
            foreach ($workerCompletedJobs as $jobId) {
                $completedJobs[$jobId] = $this->getJob($jobId, $unserialize);
            }
        }

        return $completedJobs;
    }

    /**
     * Check if worker has failed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasFailedJob(mixed $jobId): bool
    {
        return ($this->redis->get($this->prefix . $jobId . '-failed') !== false);
    }

    /**
     * Get failed job from worker by job ID
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
     * Check if worker adapter has failed jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasFailedJobs(mixed $worker): bool
    {
        $workerName       = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerFailedJobs = $this->redis->get($this->prefix . $workerName . '-failed');

        if ($workerFailedJobs !== false) {
            $workerFailedJobs = unserialize($workerFailedJobs);
            return (count($workerFailedJobs) > 0);
        } else {
            return false;
        }
    }

    /**
     * Get worker jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJobs(mixed $worker, bool $unserialize = true): array
    {
        $workerName       = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerFailedJobs = $this->redis->get($this->prefix . $workerName . '-failed');
        $failedJobs       = [];

        if ($workerFailedJobs !== false) {
            $workerFailedJobs = unserialize($workerFailedJobs);
            foreach ($workerFailedJobs as $jobId) {
                $failedJobs[$jobId] = $this->getFailedJob($jobId, $unserialize);
            }
        }

        return $failedJobs;
    }

    /**
     * Push job onto worker
     *
     * @param  mixed $worker
     * @param  mixed $job
     * @param  mixed $priority
     * @return string
     */
    public function push(mixed $worker, mixed $job, mixed $priority = null): string
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $jobId      = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();
        $jobData    = [
            'job_id'       => $jobId,
            'worker'        => $workerName,
            'priority'     => $priority,
            'max_attempts' => $job->getMaxAttempts(),
            'attempts'     => 0,
            'completed'    => null
        ];

        return $this->saveJob($workerName, $job, $jobData);
    }

    /**
     * Move failed job to failed worker
     *
     * @param  mixed           $worker
     * @param  mixed           $failedJob
     * @param  \Exception|null $exception
     * @return void
     */
    public function failed(mixed $worker, mixed $failedJob, \Exception|null $exception = null): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $failedJobData = [
            'job_id'    => $failedJob,
            'worker'    => $workerName,
            'exception' => ($exception !== null) ? $exception->getMessage() : null,
            'failed'    => date('Y-m-d H:i:s')
        ];

        $workerJobsFailed = $this->redis->get($this->prefix . $workerName . '-failed');
        $workerJobsFailed = ($workerJobsFailed !== false) ? unserialize($workerJobsFailed) : [];

        if (!in_array($failedJob, $workerJobsFailed)) {
            $workerJobsFailed[] = $failedJob;
        }

        $this->redis->set($this->prefix . $failedJob . '-failed', serialize($failedJobData));
        $this->redis->set($this->prefix . $workerName . '-failed', serialize($workerJobsFailed));

        if (!empty($failedJob)) {
            $this->pop($failedJob);
        }
    }

    /**
     * Pop job off of worker
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop(mixed $jobId): void
    {
        $jobData = $this->getJob($jobId);

        if (!empty($jobData)) {
            $workerJobs = $this->redis->get($this->prefix . $jobData['worker']);
            $workerJobs = ($workerJobs !== false) ? unserialize($workerJobs) : [];

            if (in_array($jobId, $workerJobs)) {
                unset($workerJobs[array_search($jobId, $workerJobs)]);
                $workerJobs = array_values($workerJobs);
            }

            $this->redis->set($this->prefix . $jobData['worker'], serialize($workerJobs));
        }

        $this->redis->del($this->prefix . $jobId);
    }

    /**
     * Clear jobs off of the worker
     *
     * @param  mixed $worker
     * @param  bool  $all
     * @return void
     */
    public function clear(mixed $worker, bool $all = false): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerJobs = $this->redis->get($this->prefix . $workerName);

        if ($workerJobs !== false) {
            $workerJobs = unserialize($workerJobs);
            foreach ($workerJobs as $jobId) {
                $this->redis->del($this->prefix . $jobId);
            }
        }

        if ($all) {
            $this->redis->del($this->prefix . $workerName . '-completed');
        }
    }

    /**
     * Clear failed jobs off of the worker
     *
     * @param  mixed $worker
     * @return void
     */
    public function clearFailed(mixed $worker): void
    {
        $workerName       = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $workerFailedJobs = $this->redis->get($this->prefix . $workerName . '-failed');

        if ($workerFailedJobs !== false) {
            $workerFailedJobs = unserialize($workerFailedJobs);
            foreach ($workerFailedJobs as $failedJobId) {
                $this->redis->del($this->prefix . $failedJobId . '-failed');
            }
        }

        $this->redis->del($this->prefix . $workerName . '-failed');
    }

    /**
     * Flush all jobs off of the worker
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
     * Flush all failed jobs off of the worker
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
     * Flush all worker items
     *
     * @return void
     */
    public function flushAll(): void
    {
        $this->redis->del($this->redis->keys($this->prefix . '*'));
    }

}
