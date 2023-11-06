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
 * File worker adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class File extends AbstractAdapter
{

    /**
     * Worker folder
     * @var ?string
     */
    protected ?string $folder = null;

    /**
     * Constructor
     *
     * Instantiate the file worker object
     *
     * @param string $folder
     * @throws Exception
     */
    public function __construct(string $folder)
    {
        if (!file_exists($folder)) {
            throw new Exception("Error: The worker folder '" . $folder . "' does not exist.");
        }
        if (!is_writable($folder)) {
            throw new Exception("Error: The worker folder '" . $folder . "' is not writable.");
        }

        $this->folder = $folder;
    }

    /**
     * Create file adapter
     *
     * @param  string $folder
     * @throws Exception
     * @return File
     */
    public static function create(string $folder): File
    {
        return new self($folder);
    }

    /**
     * Get all workers currently registered with this adapter
     *
     * @return array
     */
    public function getWorkers(): array
    {
        return $this->getFolders($this->folder);
    }

    /**
     * Check if worker stack has job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasJob(mixed $jobId): bool
    {
        $workerFolders = $this->getFolders($this->folder);
        $hasJob        = false;

        foreach ($workerFolders as $workerFolder) {
            if (file_exists($this->folder . '/' . $workerFolder . '/' . $jobId)) {
                $hasJob = true;
                break;
            }
        }

        return $hasJob;
    }

    /**
     * Get job from worker stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getJob(mixed $jobId, bool $unserialize = true): array
    {
        $workerFolders = $this->getFolders($this->folder);
        $job           = [];

        foreach ($workerFolders as $workerFolder) {
            if (file_exists($this->folder . '/' . $workerFolder . '/' . $jobId)) {
                $job = unserialize(file_get_contents($this->folder . '/' . $workerFolder . '/' . $jobId));
                if (file_exists($this->folder . '/' . $workerFolder . '/' . $jobId . '-payload')) {
                    $jobPayload = file_get_contents($this->folder . '/' . $workerFolder . '/' . $jobId . '-payload');
                    if ($jobPayload !== false) {
                        $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
                    }
                }
                break;
            }
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
        $jobId = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();

        file_put_contents($this->folder . '/' . $workerName . '/' . $jobId, serialize($jobData));
        file_put_contents($this->folder . '/' . $workerName . '/' . $jobId . '-payload', serialize(clone $job));

        return $jobId;
    }

    /**
     * Update job from worker stack by job ID
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
            $workerName = $jobData['worker'];
            if (isset($jobData['payload'])) {
                $jobData['payload'] = $job;
            }
            if (isset($jobData['attempts'])) {
                $jobData['attempts'] = $job->getAttempts();
            }
            if (!empty($completed)) {
                $jobData['completed'] = date('Y-m-d H:i:s', $completed);

                $this->saveJob($workerName, $job, $jobData);

                file_put_contents($this->folder . '/' . $workerName . '/completed/' . $jobId . '-' . $completed, serialize($jobData));
                if ((!$job->isValid()) && file_exists($this->folder . '/' . $workerName . '/' . $jobId)) {
                    unlink($this->folder . '/' . $workerName . '/' . $jobId);
                }
            } else {
                file_put_contents($this->folder . '/' . $workerName . '/' . $jobId, serialize($jobData));
            }
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
        return (count($this->getFiles($this->folder . '/' . $workerName)) > 0);
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
        $workerJobs = $this->getFiles($this->folder . '/' . $workerName);
        $jobs       = [];

        if (count($workerJobs) > 0) {
            foreach ($workerJobs as $jobId) {
                if ((!str_contains($jobId, '-payload')) && file_exists($this->folder . '/' . $workerName . '/' . $jobId)) {
                    $job = unserialize(file_get_contents($this->folder . '/' . $workerName . '/' . $jobId));
                    if (file_exists($this->folder . '/' . $workerName . '/' . $jobId . '-payload')) {
                        $jobPayload = file_get_contents($this->folder . '/' . $workerName . '/' . $jobId . '-payload');
                        if ($unserialize) {
                            $jobPayload = unserialize($jobPayload);
                        }

                        $job['payload'] = $jobPayload;
                    }

                    $jobs[$jobId] = $job;
                }
            }
        }

        return $jobs;
    }

    /**
     * Check if worker stack has completed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasCompletedJob(mixed $jobId): bool
    {
        $workerFolders  = $this->getFolders($this->folder);
        $hasCompleteJob = false;

        foreach ($workerFolders as $workerFolder) {
            if (file_exists($this->folder . '/' . $workerFolder . '/completed')) {
                $completedFiles = $this->getFiles($this->folder . '/' . $workerFolder . '/completed');
                foreach ($completedFiles as $completedFile) {
                    if (str_starts_with($completedFile, $jobId)) {
                        $hasCompleteJob = true;
                        break;
                    }
                }
            }
        }

        return $hasCompleteJob;
    }

    /**
     * Check if worker has completed jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasCompletedJobs(mixed $worker): bool
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        return (count($this->getFiles($this->folder . '/' . $workerName . '/completed')) > 0);
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
        $workerFolders = $this->getFolders($this->folder);
        $job           = [];

        foreach ($workerFolders as $workerFolder) {
            if (file_exists($this->folder . '/' . $workerFolder . '/completed')) {
                $completedFiles = $this->getFiles($this->folder . '/' . $workerFolder . '/completed');
                foreach ($completedFiles as $completedFile) {
                    if (str_starts_with($completedFile, $jobId)) {
                        $job = unserialize(
                            file_get_contents($this->folder . '/' . $workerFolder . '/completed/' . $completedFile)
                        );
                        if (file_exists($this->folder . '/' . $workerFolder . '/' . $jobId . '-payload')) {
                            $jobPayload = file_get_contents($this->folder . '/' . $workerFolder . '/' . $jobId . '-payload');
                            if ($jobPayload !== false) {
                                $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
                            }
                        }
                        break;
                    }
                }
            }
        }

        return $job;
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
        $workerCompletedJobs = $this->getFiles($this->folder . '/' . $workerName . '/completed');
        $completedJobs       = [];

        if (count($workerCompletedJobs) > 0) {
            foreach ($workerCompletedJobs as $jobId) {
                if (str_contains($jobId, '-')) {
                    $jobId = substr($jobId, 0, strpos($jobId, '-'));
                }
                $completedJobs[$jobId] = $this->getCompletedJob($jobId);
            }
        }

        return $completedJobs;
    }

    /**
     * Check if worker stack has failed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasFailedJob(mixed $jobId): bool
    {
        $workerFolders = $this->getFolders($this->folder);
        $hasJob        = false;

        foreach ($workerFolders as $workerFolder) {
            if (file_exists($this->folder . '/' . $workerFolder . '/failed/' . $jobId)) {
                $hasJob = true;
                break;
            }
        }

        return $hasJob;
    }

    /**
     * Get failed job from worker stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJob(mixed $jobId, bool $unserialize = true): array
    {
        $workerFolders = $this->getFolders($this->folder);
        $job           = [];

        foreach ($workerFolders as $workerFolder) {
            if (file_exists($this->folder . '/' . $workerFolder . '/failed/' . $jobId)) {
                $job = unserialize(file_get_contents($this->folder . '/' . $workerFolder . '/failed/' . $jobId));
                if (file_exists($this->folder . '/' . $workerFolder . '/' . $jobId . '-payload')) {
                    $jobPayload = file_get_contents($this->folder . '/' . $workerFolder . '/' . $jobId . '-payload');
                    if ($jobPayload !== false) {
                        $job['payload'] = ($unserialize) ? unserialize($jobPayload) : $jobPayload;
                    }
                }
                break;
            }
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
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        return (count($this->getFiles($this->folder . '/' . $workerName . '/failed')) > 0);
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
        $workerFailedJobs = $this->getFiles($this->folder . '/' . $workerName . '/failed');
        $failedJobs       = [];

        if (count($workerFailedJobs) > 0) {
            foreach ($workerFailedJobs as $jobId) {
                $failedJobs[$jobId] = $this->getFailedJob($jobId);
            }
        }

        return $failedJobs;
    }

    /**
     * Push job onto worker stack
     *
     * @param  mixed $worker
     * @param  mixed $job
     * @param  mixed $priority
     * @return string
     */
    public function push(mixed $worker, mixed $job, mixed $priority = null) : string
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $jobId      = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();

        $this->initFolders($workerName);

        $jobData = [
            'job_id'    => $jobId,
            'worker'    => $workerName,
            'priority'  => $priority,
            'attempts'  => 0,
            'completed' => null
        ];

        return $this->saveJob($workerName, $job, $jobData);
    }

    /**
     * Move failed job to failed worker stack
     *
     * @param  mixed           $worker
     * @param  mixed           $failedJob
     * @param  \Exception|null $exception
     * @return void
     */
    public function failed(mixed $worker, mixed $failedJob, \Exception|null $exception = null): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $this->initFolders($workerName);

        $failedJobData = [
            'job_id'    => $failedJob,
            'worker'     => $workerName,
            'exception' => ($exception !== null) ? $exception->getMessage() : null,
            'failed'    => date('Y-m-d H:i:s')
        ];

        file_put_contents($this->folder . '/' . $workerName . '/failed/' . $failedJob, serialize($failedJobData));

        if (!empty($failedJob)) {
            $this->pop($failedJob, false);
        }
    }

    /**
     * Pop job off of worker stack
     *
     * @param  mixed $jobId
     * @param  bool  $payload
     * @param  bool  $failed
     * @return void
     */
    public function pop(mixed $jobId, bool $payload = true, bool $failed = false): void
    {
        $jobData    = $this->getJob($jobId);
        $workerName = $jobData['worker'];

        if (file_exists($this->folder . '/' . $workerName . '/' . $jobId)) {
            unlink($this->folder . '/' . $workerName . '/' . $jobId);
        }
        if (($payload) && file_exists($this->folder . '/' . $workerName . '/' . $jobId . '-payload')) {
            unlink($this->folder . '/' . $workerName . '/' . $jobId . '-payload');
        }
        if (($failed) && file_exists($this->folder . '/' . $workerName . '/failed/' . $jobId)) {
            unlink($this->folder . '/' . $workerName . '/failed/' . $jobId);
        }
        $completeFiles = $this->getFiles($this->folder . '/' . $workerName . '/completed');
        foreach ($completeFiles as $completeFile) {
            if (file_exists($this->folder . '/' . $workerName . '/completed/' . $completeFile)) {
                unlink($this->folder . '/' . $workerName . '/completed/' . $completeFile);
            }
        }

    }

    /**
     * Clear jobs off of the worker stack
     *
     * @param  mixed $worker
     * @param  bool  $all
     * @return void
     */
    public function clear(mixed $worker, bool $all = false): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        if (file_exists($this->folder . '/' . $workerName)) {
            $this->clearFolder($this->folder . '/' . $workerName);
        }

        if (file_exists($this->folder . '/' . $workerName . '/completed')) {
            $this->clearFolder($this->folder . '/' . $workerName . '/completed');
            if (($all) && is_dir($this->folder . '/' . $workerName . '/completed') &&
                count(scandir($this->folder . '/' . $workerName . '/completed')) == 2) {
                rmdir($this->folder . '/' . $workerName . '/completed');
            }
        }

        if (file_exists($this->folder . '/' . $workerName . '/failed')) {
            $this->clearFolder($this->folder . '/' . $workerName . '/failed');
            if (($all) && is_dir($this->folder . '/' . $workerName . '/failed') &&
                count(scandir($this->folder . '/' . $workerName . '/failed')) == 2) {
                rmdir($this->folder . '/' . $workerName . '/failed');
            }
        }

        if (($all) && is_dir($this->folder . '/' . $workerName) &&
            count(scandir($this->folder . '/' . $workerName)) == 2) {
            rmdir($this->folder . '/' . $workerName);
        }
    }

    /**
     * Clear failed jobs off of the worker stack
     *
     * @param  mixed $worker
     * @return void
     */
    public function clearFailed(mixed $worker): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        if (file_exists($this->folder . '/' . $workerName . '/failed')) {
            $this->clearFolder($this->folder . '/' . $workerName . '/failed');
        }

        if (is_dir($this->folder . '/' . $workerName) && count(scandir($this->folder . '/' . $workerName)) == 2) {
            rmdir($this->folder . '/' . $workerName);
        }
    }

    /**
     * Flush all jobs off of the worker stack
     *
     * @param  bool $all
     * @return void
     */
    public function flush(bool $all = false): void
    {
        $workerFolders = $this->getFolders($this->folder);

        foreach ($workerFolders as $workerFolder) {
            $this->clear($workerFolder, $all);
        }
    }

    /**
     * Flush all failed jobs off of the worker stack
     *
     * @return void
     */
    public function flushFailed(): void
    {
        $workerFolders = $this->getFolders($this->folder);

        foreach ($workerFolders as $workerFolder) {
            $this->clearFailed($workerFolder);
        }
    }

    /**
     * Flush all pop worker items
     *
     * @return void
     */
    public function flushAll(): void
    {
        $this->flushFailed();
        $this->flush(true);

        $workerFolders = $this->getFolders($this->folder);

        foreach ($workerFolders as $workerFolder) {
            if (is_dir($this->folder . '/' . $workerFolder) && count(scandir($this->folder . '/' . $workerFolder)) == 2) {
                rmdir($this->folder . '/' . $workerFolder);
            }
        }
    }

    /**
     * Get the worker folder
     *
     * @return ?string
     */
    public function folder(): ?string
    {
        return $this->folder;
    }

    /**
     * Initialize worker folders
     *
     * @param  string $workerName
     * @return File
     */
    public function initFolders(string $workerName): File
    {
        if (!file_exists($this->folder . '/' . $workerName)) {
            mkdir($this->folder . '/' . $workerName);
            chmod($this->folder . '/' . $workerName, 0777);
        }
        if (!file_exists($this->folder . '/' . $workerName . '/completed')) {
            mkdir($this->folder . '/' . $workerName . '/completed');
            chmod($this->folder . '/' . $workerName . '/completed', 0777);
        }
        if (!file_exists($this->folder . '/' . $workerName . '/failed')) {
            mkdir($this->folder . '/' . $workerName . '/failed');
            chmod($this->folder . '/' . $workerName . '/failed', 0777);
        }

        return $this;
    }

    /**
     * Clear worker folder
     *
     * @param  string $folder
     * @return File
     */
    public function clearFolder(string $folder): File
    {
        $files = $this->getFiles($folder);

        foreach ($files as $file) {
            if (file_exists($folder . '/' . $file)) {
                unlink($folder . '/' . $file);
            }
        }

        return $this;
    }

    /**
     * Remove worker folder
     *
     * @param  string $workerName
     * @return File
     */
    public function removeWorkerFolder(string $workerName): File
    {
        $this->clearFolder($this->folder . '/' . $workerName . '/completed');
        $this->clearFolder($this->folder . '/' . $workerName . '/failed');
        $this->clearFolder($this->folder . '/' . $workerName);


        if (file_exists($this->folder . '/' . $workerName . '/completed')) {
            rmdir($this->folder . '/' . $workerName . '/completed');
        }
        if (file_exists($this->folder . '/' . $workerName . '/failed')) {
            rmdir($this->folder . '/' . $workerName . '/failed');
        }
        if (file_exists($this->folder . '/' . $workerName)) {
            rmdir($this->folder . '/' . $workerName);
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
