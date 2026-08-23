<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * In-memory adapter class
 *
 * A hermetic, single-process reference implementation of the adapter
 * contract. Used to prove the contract in tests independent of any real
 * storage backend, and as a fake for consumers of this package to test
 * against.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Memory extends AbstractTaskAdapter
{

    /**
     * Jobs, keyed by job ID
     * @var array
     */
    protected array $jobs = [];

    /**
     * Job metadata (sequence, status, availableAt, reservedUntil), keyed by job ID
     * @var array
     */
    protected array $meta = [];

    /**
     * Dead-letter jobs, keyed by job ID
     * @var array
     */
    protected array $dead = [];

    /**
     * Scheduled tasks, keyed by task ID
     * @var array
     */
    protected array $tasks = [];

    /**
     * Task claim state: taskId => [window, expiresAt]
     * @var array
     */
    protected array $taskClaims = [];

    /**
     * Push/reserve ordering sequence counter
     * @var int
     */
    protected int $sequence = 0;

    /**
     * Reservation lease length, in seconds
     * @var int
     */
    protected int $leaseSeconds = 60;

    /**
     * Constructor
     *
     * @param int     $leaseSeconds
     * @param ?string $priority
     */
    public function __construct(int $leaseSeconds = 60, ?string $priority = null)
    {
        $this->leaseSeconds = $leaseSeconds;
        parent::__construct($priority);
    }

    public function push(AbstractJob $job): Memory
    {
        $jobId = $job->getJobId();
        $this->jobs[$jobId] = clone $job;
        $this->meta[$jobId] = [
            'sequence'      => $this->sequence++,
            'status'        => 'pending',
            'availableAt'   => $job->getAvailableAt() ?? time(),
            'reservedUntil' => null,
        ];

        return $this;
    }

    public function reserve(): ?AbstractJob
    {
        $now      = time();
        $eligible = array_filter($this->meta, function($meta) use ($now) {
            return ($meta['availableAt'] <= $now) && (
                ($meta['status'] === 'pending') ||
                (($meta['status'] === 'reserved') && ($meta['reservedUntil'] <= $now))
            );
        });

        if (empty($eligible)) {
            return null;
        }

        uasort($eligible, function($a, $b) {
            return $this->isFilo() ? ($b['sequence'] <=> $a['sequence']) : ($a['sequence'] <=> $b['sequence']);
        });

        $jobId = array_key_first($eligible);

        $this->meta[$jobId]['status']        = 'reserved';
        $this->meta[$jobId]['reservedUntil'] = $now + $this->leaseSeconds;

        return clone $this->jobs[$jobId];
    }

    public function release(AbstractJob $job, ?int $delay = null): Memory
    {
        $jobId = $job->getJobId();
        if (!isset($this->meta[$jobId])) {
            return $this;
        }

        $delay = $delay ?? $job->getBackoffDelay();

        $this->jobs[$jobId] = clone $job;
        $this->meta[$jobId]['status']        = 'pending';
        $this->meta[$jobId]['availableAt']   = time() + $delay;
        $this->meta[$jobId]['reservedUntil'] = null;

        return $this;
    }

    public function delete(AbstractJob $job): Memory
    {
        $jobId = $job->getJobId();
        unset($this->jobs[$jobId], $this->meta[$jobId]);

        return $this;
    }

    public function bury(AbstractJob $job, ?string $reason = null): Memory
    {
        $jobId = $job->getJobId();
        unset($this->jobs[$jobId], $this->meta[$jobId]);

        $this->dead[$jobId] = [
            'job'      => clone $job,
            'reason'   => $reason,
            'buriedAt' => time(),
        ];

        return $this;
    }

    public function hasJobs(): bool
    {
        return !empty($this->jobs);
    }

    public function count(): int
    {
        return count($this->jobs);
    }

    public function clear(): Memory
    {
        $this->jobs = [];
        $this->meta = [];

        return $this;
    }

    public function hasDeadJobs(): bool
    {
        return !empty($this->dead);
    }

    public function countDead(): int
    {
        return count($this->dead);
    }

    public function getDeadJobs(bool $unserialize = true): array
    {
        $jobs = [];
        foreach ($this->dead as $jobId => $entry) {
            $jobs[$jobId] = $unserialize ? $entry['job'] : $entry;
        }

        return $jobs;
    }

    public function getDeadJob(string $jobId, bool $unserialize = true): mixed
    {
        if (!isset($this->dead[$jobId])) {
            return null;
        }

        return $unserialize ? $this->dead[$jobId]['job'] : $this->dead[$jobId];
    }

    public function retryDeadJob(string $jobId): Memory
    {
        if (isset($this->dead[$jobId])) {
            $this->push($this->dead[$jobId]['job']);
            unset($this->dead[$jobId]);
        }

        return $this;
    }

    public function deleteDeadJob(string $jobId): Memory
    {
        unset($this->dead[$jobId]);

        return $this;
    }

    public function clearDead(): Memory
    {
        $this->dead = [];

        return $this;
    }

    public function schedule(Task $task): Memory
    {
        if ($task->isValid()) {
            $this->tasks[$task->getJobId()] = clone $task;
        }

        return $this;
    }

    public function getTasks(): array
    {
        return array_keys($this->tasks);
    }

    public function getTask(string $taskId): ?Task
    {
        return isset($this->tasks[$taskId]) ? clone $this->tasks[$taskId] : null;
    }

    public function getAllTasks(): array
    {
        // Cloned per task for the same reason getTask() clones: callers must not
        // get a handle on the stored object and mutate the queue by accident.
        return array_map(fn(Task $task) => clone $task, $this->tasks);
    }

    public function updateTask(Task $task): Memory
    {
        if ($task->isValid()) {
            $this->tasks[$task->getJobId()] = clone $task;
        } else {
            $this->removeTask($task->getJobId());
        }

        return $this;
    }

    public function removeTask(string $taskId): Memory
    {
        unset($this->tasks[$taskId], $this->taskClaims[$taskId]);

        return $this;
    }

    public function getTaskCount(): int
    {
        return count($this->tasks);
    }

    public function hasTasks(): bool
    {
        return !empty($this->tasks);
    }

    public function clearTasks(): Memory
    {
        $this->tasks       = [];
        $this->taskClaims  = [];

        return $this;
    }

    public function claimTaskRun(string $taskId, string $window): bool
    {
        $now = time();

        if (isset($this->taskClaims[$taskId])) {
            [$claimedWindow, $expiresAt] = $this->taskClaims[$taskId];
            if (($claimedWindow === $window) && ($expiresAt > $now)) {
                return false;
            }
        }

        $this->taskClaims[$taskId] = [$window, $now + self::TASK_CLAIM_TTL];

        return true;
    }

}
