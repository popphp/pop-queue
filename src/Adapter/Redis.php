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

use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * Redis adapter class
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
     * Get queue length
     *
     * @return int
     */
    public function getLength(): int
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
        if (($this->redis->lPush($this->prefix, serialize(clone $job))) !== false) {
            $this->redis->lPush($this->prefix . ':status', 1);
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
        $length = $this->getLength();

        if ($this->isFilo()) {
            $status = $this->getStatus(0);
            if ($status == 1) {
                $this->redis->lSet($this->prefix . ':status', 0, 0);
                $job = $this->redis->lPop($this->prefix);
                $this->redis->lPop($this->prefix . ':status');
            }
        } else {
            $status = $this->getStatus($length - 1);
            if ($status == 1) {
                $this->redis->lSet($this->prefix . ':status', $length - 1, 0);
                $job = $this->redis->rPop($this->prefix);
                $this->redis->rPop($this->prefix . ':status');
            }
        }

        return ($job !== false) ? unserialize($job) : null;
    }

    /**
     * Push job on to queue
     *
     * @param  Task $task
     * @return Redis
     */
    public function schedule(Task $task): Redis
    {
        $this->redis->set($this->prefix . ':task-' . $task->getJobId(), serialize(clone $task));
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

}