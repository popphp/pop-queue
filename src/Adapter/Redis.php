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
     * Get queue start index
     *
     * @return int
     */
    public function getStart(): int
    {
        return 0;
    }

    /**
     * Get queue length
     *
     * @return int
     */
    public function getEnd(): int
    {
        return $this->redis->lLen($this->prefix);
    }

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    public function getStatus(int $index): int
    {
        return (int)$this->redis->lIndex($this->prefix . ':status', $index);
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return Redis
     */
    public function push(AbstractJob $job): Redis
    {
        $status = ($job->hasFailed()) ? 2 : 1;
        if ($job->isValid()) {
            if (($job->hasFailed()) && ($this->isFilo())) {
                if (($this->redis->rPush($this->prefix, serialize(clone $job))) !== false) {
                    $this->redis->rPush($this->prefix . ':status', $status);
                }
            } else {
                if (($this->redis->lPush($this->prefix, serialize(clone $job))) !== false) {
                    $this->redis->lPush($this->prefix . ':status', $status);
                }
            }
        }

        return $this;
    }

    /**
     * Pop job off of queue
     *
     * @return ?AbstractJob
     */
    public function pop(): ?AbstractJob
    {
        $job    = false;
        $length = $this->getEnd();

        if ($this->isFilo()) {
            $status = $this->getStatus(0);
            if ($status != 0) {
                $this->redis->lSet($this->prefix . ':status', 0, 0);
                $job = $this->redis->lPop($this->prefix);
                $this->redis->lPop($this->prefix . ':status');
            }
        } else {
            $status = $this->getStatus($length - 1);
            if ($status != 0) {
                $this->redis->lSet($this->prefix . ':status', $length - 1, 0);
                $job = $this->redis->rPop($this->prefix);
                $this->redis->rPop($this->prefix . ':status');
            }
        }

        return ($job !== false) ? unserialize($job) : null;
    }

    /**
     * Check if adapter has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return ($this->redis->lLen($this->prefix) > 0);
    }

    /**
     * Check if adapter has failed job
     *
     * @param int $index
     * @return bool
     */
    public function hasFailedJob(int $index): bool
    {
        return ($this->getStatus($index) == 2);
    }

    /**
     * Get failed job
     *
     * @param  int  $index
     * @param  bool $unserialize
     * @return mixed
     */
    public function getFailedJob(int $index, bool $unserialize = true): mixed
    {
        $job = null;

        if ($this->getStatus($index) == 2) {
            $job = $this->redis->lIndex($this->prefix, $index);
            if ($unserialize) {
                $job = unserialize($job);
            }
        }

        return $job;
    }

    /**
     * Check if adapter has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool
    {
        $result = false;
        $length = $this->redis->lLen($this->prefix);

        if ($length > 0) {
            for ($i = 0; $i < $length; $i++) {
                if ($this->getStatus($i) == 2) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Get adapter failed jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getFailedJobs(bool $unserialize = true): array
    {
        $jobs   = [];
        $length = $this->redis->lLen($this->prefix);

        if ($length > 0) {
            for ($i = 0; $i < $length; $i++) {
                if ($this->getStatus($i) == 2) {
                    $jobs[$i] = $this->getFailedJob($i, $unserialize);
                }
            }
        }

        return $jobs;
    }

    /**
     * Clear failed jobs out of the queue
     *
     * @return Redis
     */
    public function clearFailed(): Redis
    {
        $length = $this->redis->lLen($this->prefix);

        if ($length > 0) {
            for ($i = 0; $i < $length; $i++) {
                if ($this->getStatus($i) == 2) {
                    $this->redis->lRem($this->prefix, $this->redis->lIndex($this->prefix, $i));
                    $this->redis->lRem($this->prefix . ':status', $this->redis->lIndex($this->prefix . ':status', $i));
                }
            }
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


    /**
     * Clear jobs out of queue
     *
     * @return Redis
     */
    public function clear(): Redis
    {
        $taskIds = $this->redis->keys($this->prefix . ':task-*');
        foreach ($taskIds as $taskId) {
            $this->redis->del($taskId);
        }

        $this->redis->del($this->prefix . ':status');
        $this->redis->del($this->prefix);

        return $this;
    }

}
