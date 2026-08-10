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
 * @version    2.1.3
 */
class File extends AbstractTaskAdapter
{

    /**
     * Folder
     * @var ?string
     */
    protected ?string $folder = null;

    /**
     * Constructor
     *
     * Instantiate the file object
     *
     * @param  string  $folder
     * @param  ?string $priority
     * @throws Exception
     */
    public function __construct(string $folder, ?string $priority = null)
    {
        if (!file_exists($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' does not exist.");
        }
        if (!is_writable($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' is not writable.");
        }

        $this->folder = $folder;
        parent::__construct($priority);
    }

    /**
     * Create file adapter
     *
     * @param  string  $folder
     * @param  ?string $priority
     * @throws Exception
     * @return File
     */
    public static function create(string $folder, ?string $priority = null): File
    {
        return new self($folder, $priority);
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
     * Get queue start index
     *
     * @return int
     */
    protected function getStartIndex(): int
    {
        $folders = $this->getFolders($this->folder);
        return $folders[0] ?? 0;
    }

    /**
     * Get queue end index
     *
     * @return int
     */
    protected function getEndIndex(): int
    {
        $folders = $this->getFolders($this->folder);
        return (!empty($folders)) ? end($folders) : 0;
    }

    /**
     * Get queue slot status
     *
     * @param  int $index
     * @return int
     */
    protected function getSlotStatus(int $index): int
    {
        return (file_exists($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status')) ?
            (int)file_get_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status') : 0;
    }

    /**
     * Find the storage index holding a given job, if any
     *
     * @param  AbstractJob $job
     * @return ?int
     */
    protected function findIndexForJob(AbstractJob $job): ?int
    {
        foreach ($this->getFolders($this->folder) as $index) {
            $payloadFile = $this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload';
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

        if (!file_exists($this->folder . DIRECTORY_SEPARATOR . $index)) {
            mkdir($this->folder . DIRECTORY_SEPARATOR . $index);
        }
        file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload', serialize(clone $job));
        file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status', 1);

        return $this;
    }

    /**
     * Atomically claim the next eligible job
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $index  = ($this->isFifo()) ? $this->getStartIndex() : $this->getEndIndex();
        $status = $this->getSlotStatus($index);

        if ($status != 1) {
            return null;
        }

        file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status', 0);
        $payload = file_get_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload');

        return unserialize($payload);
    }

    /**
     * Put a job back to pending
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return File
     */
    public function release(AbstractJob $job, ?int $delay = null): File
    {
        $index = $this->findIndexForJob($job);
        if ($index === null) {
            return $this;
        }

        file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload', serialize(clone $job));
        file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status', 1);

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
        $index = $this->findIndexForJob($job);
        if ($index === null) {
            return $this;
        }

        $folder = $this->folder . DIRECTORY_SEPARATOR . $index;
        if (file_exists($folder . DIRECTORY_SEPARATOR . 'payload')) {
            unlink($folder . DIRECTORY_SEPARATOR . 'payload');
        }
        if (file_exists($folder . DIRECTORY_SEPARATOR . 'status')) {
            unlink($folder . DIRECTORY_SEPARATOR . 'status');
        }
        rmdir($folder);

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
        return !empty($this->getFolders($this->folder));
    }

    /**
     * Count of pending + reserved jobs
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->getFolders($this->folder));
    }

    /**
     * Clear pending and reserved jobs (not tasks or dead-letter jobs)
     *
     * @return File
     */
    public function clear(): File
    {
        foreach ($this->getFolders($this->folder) as $folder) {
            $path = $this->folder . DIRECTORY_SEPARATOR . $folder;
            if (file_exists($path . DIRECTORY_SEPARATOR . 'payload')) {
                unlink($path . DIRECTORY_SEPARATOR . 'payload');
            }
            if (file_exists($path . DIRECTORY_SEPARATOR . 'status')) {
                unlink($path . DIRECTORY_SEPARATOR . 'status');
            }
            rmdir($path);
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
