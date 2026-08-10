<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * Redis adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.3
 */
class Redis extends AbstractTaskAdapter
{

    /**
     * Redis object
     * @var \Redis|null
     */
    protected \Redis|null $redis = null;


    /**
     * Queue prefix
     * @var string
     */
    protected string $prefix = 'pop-queue';

    /**
     * Constructor
     *
     * Instantiate the redis adapter
     *
     * @param  string     $host
     * @param  int|string $port
     * @param  string     $prefix
     * @param  ?string    $priority
     * @throws Exception|\RedisException
     */
    public function __construct(
        string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-queue', ?string $priority = null
    )
    {
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis  = new \Redis();
        $this->prefix = $prefix;
        if (!$this->redis->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the redis server.');
        }

        parent::__construct($priority);
    }

    /**
     * Create Redis adapter
     *
     * @param  string     $host
     * @param  int|string $port
     * @param  string     $prefix
     * @param  ?string    $priority
     * @throws Exception|\RedisException
     * @return Redis
     */
    public static function create(
        string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-queue', ?string $priority = null
    ): Redis
    {
        return new self($host, $port, $prefix, $priority);
    }

    /**
     * Get Redis object
     *
     * @return \Redis|null
     */
    public function getRedis(): \Redis|null
    {
        return $this->redis;
    }

    /**
     * Get Redis object (alias)
     *
     * @return \Redis|null
     */
    public function redis(): \Redis|null
    {
        return $this->redis;
    }

    /**
     * Get prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Remove the first job matching a job ID from a given list
     *
     * @param  string $key
     * @param  string $jobId
     * @return void
     */
    protected function removeFromList(string $key, string $jobId): void
    {
        foreach ($this->redis->lRange($key, 0, -1) as $value) {
            $stored = unserialize($value);
            if (($stored instanceof AbstractJob) && ($stored->getJobId() === $jobId)) {
                $this->redis->lRem($key, $value, 1);
                break;
            }
        }
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return Redis
     */
    public function push(AbstractJob $job): Redis
    {
        $job->getJobId();
        $this->redis->lPush($this->prefix, serialize(clone $job));
        return $this;
    }

    /**
     * Atomically claim the next eligible job
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $index = $this->isFifo() ? -1 : 0;
        $value = $this->redis->lIndex($this->prefix, $index);

        if ($value === false) {
            return null;
        }

        $this->redis->lRem($this->prefix, $value, 1);
        $this->redis->rPush($this->prefix . ':reserved', $value);

        return unserialize($value);
    }

    /**
     * Put a job back to pending
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return Redis
     */
    public function release(AbstractJob $job, ?int $delay = null): Redis
    {
        $this->removeFromList($this->prefix . ':reserved', $job->getJobId());
        $this->redis->lPush($this->prefix, serialize(clone $job));

        return $this;
    }

    /**
     * Permanently remove a job
     *
     * @param  AbstractJob $job
     * @return Redis
     */
    public function delete(AbstractJob $job): Redis
    {
        $this->removeFromList($this->prefix . ':reserved', $job->getJobId());
        return $this;
    }

    /**
     * Move a job to the dead-letter store
     *
     * @param  AbstractJob $job
     * @param  ?string     $reason
     * @return Redis
     */
    public function bury(AbstractJob $job, ?string $reason = null): Redis
    {
        $this->removeFromList($this->prefix . ':reserved', $job->getJobId());
        $this->redis->set($this->prefix . ':dead-' . $job->getJobId(), serialize(clone $job));

        return $this;
    }

    /**
     * Check if adapter has pending or reserved jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return ($this->count() > 0);
    }

    /**
     * Count of pending + reserved jobs
     *
     * @return int
     */
    public function count(): int
    {
        return $this->redis->lLen($this->prefix) + $this->redis->lLen($this->prefix . ':reserved');
    }

    /**
     * Clear pending and reserved jobs (not tasks or dead-letter jobs)
     *
     * @return Redis
     */
    public function clear(): Redis
    {
        $this->redis->del($this->prefix);
        $this->redis->del($this->prefix . ':reserved');

        return $this;
    }

    /**
     * Check if adapter has dead jobs
     *
     * @return bool
     */
    public function hasDeadJobs(): bool
    {
        return !empty($this->redis->keys($this->prefix . ':dead-*'));
    }

    /**
     * Count of dead jobs
     *
     * @return int
     */
    public function countDead(): int
    {
        return count($this->redis->keys($this->prefix . ':dead-*'));
    }

    /**
     * Get dead jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getDeadJobs(bool $unserialize = true): array
    {
        $jobs = [];
        foreach ($this->redis->keys($this->prefix . ':dead-*') as $key) {
            $jobId = substr($key, strrpos($key, ':dead-') + 6);
            $jobs[$jobId] = $this->getDeadJob($jobId, $unserialize);
        }

        return $jobs;
    }

    /**
     * Get a dead job
     *
     * @param  string $jobId
     * @param  bool   $unserialize
     * @return mixed
     */
    public function getDeadJob(string $jobId, bool $unserialize = true): mixed
    {
        $value = $this->redis->get($this->prefix . ':dead-' . $jobId);
        if ($value === false) {
            return null;
        }

        return $unserialize ? unserialize($value) : $value;
    }

    /**
     * Retry a dead job by pushing it back on to the queue
     *
     * @param  string $jobId
     * @return Redis
     */
    public function retryDeadJob(string $jobId): Redis
    {
        $job = $this->getDeadJob($jobId);
        if ($job instanceof AbstractJob) {
            $this->deleteDeadJob($jobId);
            $this->push($job);
        }

        return $this;
    }

    /**
     * Permanently delete a dead job
     *
     * @param  string $jobId
     * @return Redis
     */
    public function deleteDeadJob(string $jobId): Redis
    {
        $this->redis->del($this->prefix . ':dead-' . $jobId);
        return $this;
    }

    /**
     * Clear all dead jobs
     *
     * @return Redis
     */
    public function clearDead(): Redis
    {
        foreach ($this->redis->keys($this->prefix . ':dead-*') as $key) {
            $this->redis->del($key);
        }

        return $this;
    }

    /**
     * Push job on to queue
     *
     * @param  Task $task
     * @return Redis
     */
    public function schedule(Task $task): Redis
    {
        if ($task->isValid()) {
            $this->redis->set($this->prefix . ':task-' . $task->getJobId(), serialize(clone $task));
        }
        return $this;
    }

    /**
     * Get scheduled tasks
     *
     * @return array
     */
    public function getTasks(): array
    {
        $taskIds = $this->redis->keys($this->prefix . ':task-*');
        return array_map(function($value) {
            return substr($value, (strpos($value, ':task-') + 6));
        }, $taskIds);
    }

    /**
     * Get scheduled task
     *
     * @param  string $taskId
     * @return ?Task
     */
    public function getTask(string $taskId): ?Task
    {
        $task = $this->redis->get($this->prefix . ':task-' . $taskId);
        return ($task !== false) ? unserialize($task) : null;
    }

    /**
     * Update scheduled task
     *
     * @param  Task $task
     * @return Redis
     */
    public function updateTask(Task $task): Redis
    {
        if ($task->isValid()) {
            $this->redis->set($this->prefix . ':task-' . $task->getJobId(), serialize(clone $task));
        } else {
            $this->removeTask($task->getJobId());
        }
        return $this;
    }

    /**
     * Remove scheduled task
     *
     * @param  string $taskId
     * @return Redis
     */
    public function removeTask(string $taskId): Redis
    {
        $this->redis->del($this->prefix . ':task-' . $taskId);
        return $this;
    }

    /**
     * Get scheduled tasks count
     *
     * @return int
     */
    public function getTaskCount(): int
    {
        $taskIds = $this->redis->keys($this->prefix . ':task-*');
        return count(array_map(function($value) {
            return substr($value, (strpos($value, ':task-') + 6));
        }, $taskIds));
    }

    /**
     * Has scheduled tasks
     *
     * @return bool
     */
    public function hasTasks(): bool
    {
        $taskIds = $this->redis->keys($this->prefix . ':task-*');
        return !empty(array_map(function($value) {
            return substr($value, (strpos($value, ':task-') + 6));
        }, $taskIds));
    }

    /**
     * Clear all scheduled task
     *
     * @return Redis
     */
    public function clearTasks(): Redis
    {
        $taskIds = $this->getTasks();

        foreach ($taskIds as $taskId) {
            $this->removeTask($taskId);
        }
        return $this;
    }

}
