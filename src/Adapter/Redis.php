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
     * Reservation lease length, in seconds
     * @var int
     */
    protected int $leaseSeconds = 60;

    /**
     * Constructor
     *
     * Instantiate the redis adapter
     *
     * @param  string     $host
     * @param  int|string $port
     * @param  string     $prefix
     * @param  ?string    $priority
     * @param  int        $leaseSeconds
     * @param  ?string    $password     Optional password for AUTH
     * @param  ?array     $context      Optional stream context (e.g. ['stream' => ['ssl' => [...]]] for TLS)
     * @throws Exception|\RedisException
     */
    public function __construct(
        string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-queue', ?string $priority = null,
        int $leaseSeconds = 60, ?string $password = null, ?array $context = null
    )
    {
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis        = new \Redis();
        $this->prefix        = $prefix;
        $this->leaseSeconds  = $leaseSeconds;

        if (!$this->redis->connect($host, (int)$port, context: $context)) {
            throw new Exception('Error: Unable to connect to the redis server.');
        }

        if (($password !== null) && !$this->redis->auth($password)) {
            throw new Exception('Error: Unable to authenticate with the redis server.');
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
     * @param  int        $leaseSeconds
     * @param  ?string    $password
     * @param  ?array     $context
     * @throws Exception|\RedisException
     * @return Redis
     */
    public static function create(
        string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-queue', ?string $priority = null,
        int $leaseSeconds = 60, ?string $password = null, ?array $context = null
    ): Redis
    {
        return new self($host, $port, $prefix, $priority, $leaseSeconds, $password, $context);
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
     * Find a job matching a job ID in the reserved sorted set and remove it
     *
     * @param  string $jobId
     * @return ?string  the removed member's serialized value, if found
     */
    protected function removeFromReserved(string $jobId): ?string
    {
        foreach ($this->redis->zRange($this->prefix . ':reserved', 0, -1) as $value) {
            $stored = unserialize($value);
            if (($stored instanceof AbstractJob) && ($stored->getJobId() === $jobId)) {
                $this->redis->zRem($this->prefix . ':reserved', $value);
                return $value;
            }
        }

        return null;
    }

    /**
     * Atomically remove a reserved-set member if, and only if, its *current*
     * score (re-checked server-side at the moment this runs, not a stale
     * snapshot from an earlier zRangeByScore() read) is still <= $now.
     *
     * This closes an ABA race: a worker's earlier "this looks expired" read
     * can go stale if another worker reclaims and freshly re-claims the
     * same entry before the first worker acts on it. Because a reclaim
     * never re-serializes the job, the serialized value alone can't tell
     * "the same expired claim" apart from "a fresh claim that happens to
     * match" - a plain zRem($key, $value) would remove the fresh claim too,
     * since it matches by value only and ignores the current score. Redis
     * executes Lua scripts atomically, so this re-check-and-remove can't be
     * interleaved by another command.
     *
     * @param  string $value
     * @param  int    $now
     * @return int  1 if removed, 0 if the entry is missing or no longer expired
     */
    protected function atomicReclaimIfStillExpired(string $value, int $now): int
    {
        $script = <<<'LUA'
local score = redis.call('ZSCORE', KEYS[1], ARGV[1])
if score and tonumber(score) <= tonumber(ARGV[2]) then
    redis.call('ZREM', KEYS[1], ARGV[1])
    return 1
end
return 0
LUA;

        return (int)$this->redis->eval($script, [$this->prefix . ':reserved', $value, $now], 1);
    }

    /**
     * Move any reserved job whose lease has expired back to the pending list,
     * so a crashed worker's claim self-heals instead of being stuck forever.
     *
     * @return void
     */
    protected function reclaimExpiredLeases(): void
    {
        $now     = time();
        $expired = $this->redis->zRangeByScore($this->prefix . ':reserved', '-inf', (string)$now);

        foreach ($expired as $value) {
            // atomicReclaimIfStillExpired() re-verifies the *current* score
            // at removal time; if it reports 0, another worker already
            // reclaimed (and possibly freshly re-claimed) this same expired
            // lease first - skip it rather than trust our stale read.
            if ($this->atomicReclaimIfStillExpired($value, $now) === 1) {
                $this->redis->lPush($this->prefix, $value);
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
     * Atomically claim the next eligible job. Reclaims any reserved job whose
     * lease has expired first, then scans the pending list in queue order and
     * skips any job that isn't yet available, atomically claiming the first
     * eligible one via a checked lRem() - if lRem() reports it removed
     * nothing, another worker already claimed this same entry first, so this
     * moves on to the next candidate instead of assuming success.
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $this->reclaimExpiredLeases();

        $values = $this->redis->lRange($this->prefix, 0, -1);

        if (empty($values)) {
            return null;
        }

        // push() lPushes, so the oldest entry is at the tail of the list
        if ($this->isFifo()) {
            $values = array_reverse($values);
        }

        foreach ($values as $value) {
            $job = unserialize($value);

            if (($job instanceof AbstractJob) && !$job->isAvailable()) {
                continue;
            }

            if ($this->redis->lRem($this->prefix, $value, 1) !== 1) {
                continue;
            }

            $this->redis->zAdd($this->prefix . ':reserved', time() + $this->leaseSeconds, $value);

            return $job;
        }

        return null;
    }

    /**
     * Put a job back to pending, honoring its backoff schedule unless an
     * explicit delay is given
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return Redis
     */
    public function release(AbstractJob $job, ?int $delay = null): Redis
    {
        $this->removeFromReserved($job->getJobId());
        $job->delay($delay ?? $job->getBackoffDelay());
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
        $this->removeFromReserved($job->getJobId());
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
        $this->removeFromReserved($job->getJobId());
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
        return $this->redis->lLen($this->prefix) + $this->redis->zCard($this->prefix . ':reserved');
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
