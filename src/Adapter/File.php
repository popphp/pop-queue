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
 * File adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class File extends AbstractTaskAdapter
{

    /**
     * Folder
     * @var ?string
     */
    protected ?string $folder = null;

    /**
     * Reservation lease length, in seconds
     * @var int
     */
    protected int $leaseSeconds = 60;

    /**
     * Constructor
     *
     * Instantiate the file object
     *
     * @param  string  $folder
     * @param  ?string $priority
     * @param  int     $leaseSeconds
     * @throws Exception
     */
    public function __construct(string $folder, ?string $priority = null, int $leaseSeconds = 60)
    {
        if (!file_exists($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' does not exist.");
        }
        if (!is_writable($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' is not writable.");
        }

        $this->folder       = $folder;
        $this->leaseSeconds = $leaseSeconds;

        if (!file_exists($this->pendingPath())) {
            mkdir($this->pendingPath());
        }
        if (!file_exists($this->reservedPath())) {
            mkdir($this->reservedPath());
        }

        parent::__construct($priority);
    }

    /**
     * Create file adapter
     *
     * @param  string  $folder
     * @param  ?string $priority
     * @param  int     $leaseSeconds
     * @throws Exception
     * @return File
     */
    public static function create(string $folder, ?string $priority = null, int $leaseSeconds = 60): File
    {
        return new self($folder, $priority, $leaseSeconds);
    }

    /**
     * Get folder
     *
     * @return ?string
     */
    public function getFolder(): ?string
    {
        return $this->folder;
    }

    /**
     * Get folder (alias)
     *
     * @return ?string
     */
    public function folder(): ?string
    {
        return $this->folder;
    }

    /**
     * Get the pending-jobs subdirectory path
     *
     * @return string
     */
    protected function pendingPath(): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'pending';
    }

    /**
     * Get the reserved-jobs subdirectory path
     *
     * @return string
     */
    protected function reservedPath(): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'reserved';
    }

    /**
     * Get queue end index across both pending and reserved jobs (indices must
     * stay unique across both so a reclaimed reserved job can never collide
     * with a newly-pushed one)
     *
     * @return int
     */
    protected function getEndIndex(): int
    {
        $indices = array_merge(
            array_map('intval', $this->getFolders($this->pendingPath())),
            array_map('intval', $this->getFolders($this->reservedPath()))
        );

        return !empty($indices) ? max($indices) : 0;
    }

    /**
     * Read a reserved job's lease expiry, if any
     *
     * @param  string $reservedDir
     * @return ?int
     */
    protected function getLeaseUntil(string $reservedDir): ?int
    {
        $leaseFile = $reservedDir . DIRECTORY_SEPARATOR . 'lease';
        return file_exists($leaseFile) ? (int)file_get_contents($leaseFile) : null;
    }

    /**
     * Move any reserved job whose lease has expired back to pending, so a
     * crashed worker's claim self-heals instead of being stuck forever.
     * Reclaimed jobs are eligible on this or a later reserve() call, not
     * necessarily returned by this one.
     *
     * @return void
     */
    protected function reclaimExpiredLeases(): void
    {
        $now = time();

        foreach ($this->getFolders($this->reservedPath()) as $index) {
            $reservedDir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;
            $leaseUntil  = $this->getLeaseUntil($reservedDir);

            if (($leaseUntil !== null) && ($leaseUntil <= $now)) {
                $pendingDir = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;
                // rename() is atomic; if it fails, another worker already
                // reclaimed this same expired lease first - safe to skip.
                @rename($reservedDir, $pendingDir);
            }
        }
    }

    /**
     * Find the reserved-job directory index holding a given job, if any
     *
     * @param  AbstractJob $job
     * @return ?int
     */
    protected function findReservedIndexForJob(AbstractJob $job): ?int
    {
        foreach ($this->getFolders($this->reservedPath()) as $index) {
            $payloadFile = $this->reservedPath() . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload';
            if (file_exists($payloadFile)) {
                $stored = unserialize(file_get_contents($payloadFile));
                if (($stored instanceof AbstractJob) && ($stored->getJobId() === $job->getJobId())) {
                    return (int)$index;
                }
            }
        }

        return null;
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return File
     */
    public function push(AbstractJob $job): File
    {
        // Force job ID generation before persisting so identity survives the
        // serialize/unserialize round-trip on subsequent reserve/release/delete calls.
        $job->getJobId();

        $index = $this->getEndIndex() + 1;
        $dir   = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;

        if (!file_exists($dir)) {
            mkdir($dir);
        }
        file_put_contents($dir . DIRECTORY_SEPARATOR . 'payload', serialize(clone $job));

        return $this;
    }

    /**
     * Atomically claim the next eligible job. Reclaims any reserved job whose
     * lease has expired first, then scans pending jobs in FIFO/FILO order,
     * skipping any that aren't yet available, and atomically claims the first
     * eligible one via rename() - if the rename fails, another worker won the
     * race and this moves on to the next candidate.
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $this->reclaimExpiredLeases();

        $indices = array_map('intval', $this->getFolders($this->pendingPath()));
        usort($indices, function($a, $b) {
            return $this->isFifo() ? ($a <=> $b) : ($b <=> $a);
        });

        foreach ($indices as $index) {
            $pendingDir  = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;
            $payloadFile = $pendingDir . DIRECTORY_SEPARATOR . 'payload';

            if (!file_exists($payloadFile)) {
                continue;
            }

            $job = unserialize(file_get_contents($payloadFile));

            if (($job instanceof AbstractJob) && !$job->isAvailable()) {
                continue;
            }

            $reservedDir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;
            if (!@rename($pendingDir, $reservedDir)) {
                // Lost the race to another worker - move on.
                continue;
            }

            file_put_contents($reservedDir . DIRECTORY_SEPARATOR . 'lease', (string)(time() + $this->leaseSeconds));

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
     * @return File
     */
    public function release(AbstractJob $job, ?int $delay = null): File
    {
        $index = $this->findReservedIndexForJob($job);
        if ($index === null) {
            return $this;
        }

        $job->delay($delay ?? $job->getBackoffDelay());

        $reservedDir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;
        $pendingDir  = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;

        file_put_contents($reservedDir . DIRECTORY_SEPARATOR . 'payload', serialize(clone $job));
        rename($reservedDir, $pendingDir);

        return $this;
    }

    /**
     * Permanently remove a job
     *
     * @param  AbstractJob $job
     * @return File
     */
    public function delete(AbstractJob $job): File
    {
        $index = $this->findReservedIndexForJob($job);
        if ($index === null) {
            return $this;
        }

        $dir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;
        if (file_exists($dir . DIRECTORY_SEPARATOR . 'payload')) {
            unlink($dir . DIRECTORY_SEPARATOR . 'payload');
        }
        if (file_exists($dir . DIRECTORY_SEPARATOR . 'lease')) {
            unlink($dir . DIRECTORY_SEPARATOR . 'lease');
        }
        rmdir($dir);

        return $this;
    }

    /**
     * Move a job to the dead-letter store
     *
     * @param  AbstractJob $job
     * @param  ?string     $reason
     * @return File
     */
    public function bury(AbstractJob $job, ?string $reason = null): File
    {
        $this->delete($job);
        file_put_contents($this->folder . DIRECTORY_SEPARATOR . 'dead-' . $job->getJobId(), serialize(clone $job));

        return $this;
    }

    /**
     * Check if adapter has jobs
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
        return count($this->getFolders($this->pendingPath())) + count($this->getFolders($this->reservedPath()));
    }

    /**
     * Clear pending and reserved jobs (not tasks or dead-letter jobs)
     *
     * @return File
     */
    public function clear(): File
    {
        foreach ([$this->pendingPath(), $this->reservedPath()] as $path) {
            foreach ($this->getFolders($path) as $index) {
                $dir = $path . DIRECTORY_SEPARATOR . $index;
                if (file_exists($dir . DIRECTORY_SEPARATOR . 'payload')) {
                    unlink($dir . DIRECTORY_SEPARATOR . 'payload');
                }
                if (file_exists($dir . DIRECTORY_SEPARATOR . 'lease')) {
                    unlink($dir . DIRECTORY_SEPARATOR . 'lease');
                }
                rmdir($dir);
            }
        }

        return $this;
    }

    /**
     * Get the dead-letter job IDs
     *
     * @return array
     */
    protected function getDeadJobIds(): array
    {
        $ids = [];
        foreach ($this->getFiles($this->folder) as $file) {
            if (str_starts_with($file, 'dead-')) {
                $ids[] = substr($file, 5);
            }
        }

        return $ids;
    }

    /**
     * Check if adapter has dead-letter jobs
     *
     * @return bool
     */
    public function hasDeadJobs(): bool
    {
        return !empty($this->getDeadJobIds());
    }

    /**
     * Count of dead-letter jobs
     *
     * @return int
     */
    public function countDead(): int
    {
        return count($this->getDeadJobIds());
    }

    /**
     * Get dead-letter jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getDeadJobs(bool $unserialize = true): array
    {
        $jobs = [];
        foreach ($this->getDeadJobIds() as $jobId) {
            $jobs[$jobId] = $this->getDeadJob($jobId, $unserialize);
        }

        return $jobs;
    }

    /**
     * Get a dead-letter job
     *
     * @param  string $jobId
     * @param  bool   $unserialize
     * @return mixed
     */
    public function getDeadJob(string $jobId, bool $unserialize = true): mixed
    {
        $path = $this->folder . DIRECTORY_SEPARATOR . 'dead-' . $jobId;
        if (!file_exists($path)) {
            return null;
        }

        $payload = file_get_contents($path);
        return $unserialize ? unserialize($payload) : $payload;
    }

    /**
     * Move a dead-letter job back to pending
     *
     * @param  string $jobId
     * @return File
     */
    public function retryDeadJob(string $jobId): File
    {
        $job = $this->getDeadJob($jobId);
        if ($job instanceof AbstractJob) {
            $this->push($job);
            $this->deleteDeadJob($jobId);
        }

        return $this;
    }

    /**
     * Permanently remove a dead-letter job
     *
     * @param  string $jobId
     * @return File
     */
    public function deleteDeadJob(string $jobId): File
    {
        $path = $this->folder . DIRECTORY_SEPARATOR . 'dead-' . $jobId;
        if (file_exists($path)) {
            unlink($path);
        }

        return $this;
    }

    /**
     * Clear all dead-letter jobs
     *
     * @return File
     */
    public function clearDead(): File
    {
        foreach ($this->getDeadJobIds() as $jobId) {
            $this->deleteDeadJob($jobId);
        }

        return $this;
    }

    /**
     * Schedule job with queue
     *
     * @param  Task $task
     * @return File
     */
    public function schedule(Task $task): File
    {
        if ($task->isValid()) {
            file_put_contents(
                $this->folder . DIRECTORY_SEPARATOR . 'task-' . $task->getJobId(), serialize(clone $task)
            );
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
        $files = $this->getFiles($this->folder);
        $tasks = [];

        foreach ($files as $file) {
            if (str_starts_with($file, 'task-')) {
                $tasks[] = substr($file, 5);
            }
        }

        return $tasks;
    }

    /**
     * Get scheduled task
     *
     * @param  string $taskId
     * @return ?Task
     */
    public function getTask(string $taskId): ?Task
    {
        return (file_exists($this->folder . DIRECTORY_SEPARATOR . 'task-' . $taskId)) ?
            unserialize(file_get_contents($this->folder . DIRECTORY_SEPARATOR . 'task-' . $taskId)) : null;
    }

    /**
     * Update scheduled task
     *
     * @param  Task $task
     * @return File
     */
    public function updateTask(Task $task): File
    {
        if ($task->isValid()) {
            file_put_contents(
                $this->folder . DIRECTORY_SEPARATOR . 'task-' . $task->getJobId(), serialize(clone $task)
            );
        } else {
            $this->removeTask($task->getJobId());
        }

        return $this;
    }

    /**
     * Remove scheduled task
     *
     * @param  string $taskId
     * @return File
     */
    public function removeTask(string $taskId): File
    {
        if (file_exists($this->folder . DIRECTORY_SEPARATOR . 'task-' . $taskId)) {
            unlink($this->folder . DIRECTORY_SEPARATOR . 'task-' . $taskId);
        }
        return $this;
    }

    /**
     * Get scheduled tasks count
     *
     * @return int
     */
    public function getTaskCount(): int
    {
        return count($this->getTasks());
    }

    /**
     * Has scheduled tasks
     *
     * @return bool
     */
    public function hasTasks(): bool
    {
        return ($this->getTaskCount() > 0);
    }

    /**
     * Clear all scheduled task
     *
     * @return File
     */
    public function clearTasks(): File
    {
        $tasks = $this->getTasks();

        foreach ($tasks as $taskId) {
            $this->removeTask($taskId);
        }

        return $this;
    }

    /**
     * Get folders
     *
     * @param  string $folder
     * @return array
     */
    public function getFolders(string $folder): array
    {
        if (is_dir($folder)) {
            return array_values(array_filter(scandir($folder), function($value) use ($folder) {
                return (($value != '.') && ($value != '..') && ($value != '.empty') && is_dir($folder . '/' . $value));
            }));
        } else {
            return [];
        }
    }

    /**
     * Get files from folder
     *
     * @param  string $folder
     * @return array
     */
    public function getFiles(string $folder): array
    {
        if (is_dir($folder)) {
            return array_values(array_filter(scandir($folder), function($value) use ($folder) {
                return (($value != '.') && ($value != '..') && ($value != '.empty') && !is_dir($folder . '/' . $value));
            }));
        } else {
            return [];
        }
    }

}
