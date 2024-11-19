<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
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
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.0
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
    public function getStart(): int
    {
        $folders = $this->getFolders($this->folder);
        return $folders[0] ?? 0;
    }

    /**
     * Get queue end index
     *
     * @return int
     */
    public function getEnd(): int
    {
        $folders = $this->getFolders($this->folder);
        return (!empty($folders)) ? end($folders) : 0;
    }

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    public function getStatus(int $index): int
    {
        return (file_exists($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status')) ?
            file_get_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status') : 0;
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return File
     */
    public function push(AbstractJob $job): File
    {
        $status = 1;
        $index  = ($this->getEnd() + 1);

        if ($job->hasFailed()) {
            $status = 2;
            if ($this->isFilo()) {
                $index = ($this->getStart() - 1);
            }
        }

        if ($job->isValid()) {
            if (!file_exists($this->folder . DIRECTORY_SEPARATOR . $index)) {
                mkdir($this->folder . DIRECTORY_SEPARATOR . $index);
            }
            file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload', serialize(clone $job));
            file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status', $status);
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
        $index  = ($this->isFifo()) ? $this->getStart() : $this->getEnd();
        $status = $this->getStatus($index);

        if ($status != 0) {
            file_put_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status', 0);
            if (file_exists($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload')) {
                $job = file_get_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload');
                unlink($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload');
                if (file_exists($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status')) {
                    unlink($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status');
                }
                rmdir($this->folder . DIRECTORY_SEPARATOR . $index);
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
        return !empty($this->getFolders($this->folder));
    }

    /**
     * Check if adapter has failed job
     *
     * @param  int $index
     * @return bool
     */
    public function hasFailedJob(int $index): bool
    {
        return (file_exists($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status') &&
            (file_get_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'status') == 2));
    }

    /**
     * Get failed job
     *
     * @param  int $index
     * @param  bool $unserialize
     * @return mixed
     */
    public function getFailedJob(int $index, bool $unserialize = true): mixed
    {
        if (($this->hasFailedJob($index)) &&
            file_exists($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload')) {
            $payload = file_get_contents($this->folder . DIRECTORY_SEPARATOR . $index . DIRECTORY_SEPARATOR . 'payload');
            return ($unserialize) ? unserialize($payload) : $payload;
        } else {
            return null;
        }
    }

    /**
     * Check if adapter has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool
    {
        return (count($this->getFailedJobs(false)) > 0);
    }

    /**
     * Get adapter failed jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getFailedJobs(bool $unserialize = true): array
    {
        $folders = $this->getFolders($this->folder);
        $failed  = [];

        foreach ($folders as $index) {
            if ($this->hasFailedJob($index)) {
                $failed[$index] = $this->getFailedJob($index);
            }
        }

        return $failed;
    }

    /**
     * Clear failed jobs out of the queue
     *
     * @return File
     */
    public function clearFailed(): File
    {
        $failed = $this->getFailedJobs(false);

        foreach ($failed as $folder => $failedJob) {
            if (file_exists($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'payload')) {
                unlink($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'payload');
            }
            if (file_exists($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'status')) {
                unlink($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'status');
            }
            rmdir($this->folder . DIRECTORY_SEPARATOR . $folder);
        }
        return $this;
    }

    /**
     * Clear jobs out of queue
     *
     * @return File
     */
    public function clear(): File
    {
        $files   = $this->getFiles($this->folder);
        $folders = $this->getFolders($this->folder);

        foreach ($files as $file) {
            if (file_exists($this->folder . DIRECTORY_SEPARATOR . $file)) {
                unlink($this->folder . DIRECTORY_SEPARATOR . $file);
            }
        }

        foreach ($folders as $folder) {
            if (file_exists($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'payload')) {
                unlink($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'payload');
            }
            if (file_exists($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'status')) {
                unlink($this->folder . DIRECTORY_SEPARATOR . $folder . DIRECTORY_SEPARATOR . 'status');
            }
            rmdir($this->folder . DIRECTORY_SEPARATOR . $folder);
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
