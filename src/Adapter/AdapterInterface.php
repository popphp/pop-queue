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

use Pop\Queue\Processor\AbstractJob;

/**
 * Worker adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface AdapterInterface
{

    /**
     * Get all workers currently registered with this adapter
     *
     * @return array
     */
    public function getWorkers(): array;

    /**
     * Check if worker has job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasJob(mixed $jobId): bool;

    /**
     * Get job from worker by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getJob(mixed $jobId, bool $unserialize = true): array;

    /**
     * Save job with worker
     *
     * @param  string $workerName
     * @param  mixed $job
     * @param  array $jobData
     * @return string
     */
    public function saveJob(string $workerName, mixed $job, array $jobData) : string;

    /**
     * Update job from worker by job ID
     *
     * @param  AbstractJob $job
     * @return void
     */
    public function updateJob(AbstractJob $job): void;

    /**
     * Check if worker adapter has jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasJobs(mixed $worker): bool;

    /**
     * Get worker jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getJobs(mixed $worker, bool $unserialize = true): array;

    /**
     * Check if worker has completed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasCompletedJob(mixed $jobId): bool;

    /**
     * Check if worker adapter has completed jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasCompletedJobs(mixed $worker): bool;

    /**
     * Get worker completed jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJobs(mixed $worker, bool $unserialize = true): array;

    /**
     * Get worker completed job
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJob(mixed $jobId, bool $unserialize = true): array;

    /**
     * Check if worker has failed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasFailedJob(mixed $jobId): bool;

    /**
     * Get failed job from worker by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJob(mixed $jobId, bool $unserialize = true): array;

    /**
     * Check if worker adapter has failed jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasFailedJobs(mixed $worker): bool;

    /**
     * Get worker jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJobs(mixed $worker, bool $unserialize = true): array;

    /**
     * Push job onto worker
     *
     * @param  mixed $worker
     * @param  mixed $job
     * @param  mixed $priority
     * @return string
     */
    public function push(mixed $worker, mixed $job, mixed $priority = null): string;

    /**
     * Move failed job to failed worker
     *
     * @param  mixed           $worker
     * @param  mixed           $failedJob
     * @param  \Exception|null $exception
     * @return void
     */
    public function failed(mixed $worker, mixed $failedJob, \Exception|null $exception = null): void;

    /**
     * Pop job off of worker
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop(mixed $jobId): void;

    /**
     * Clear completed jobs off of the worker
     *
     * @param  mixed $worker
     * @param  bool  $all
     * @return void
     */
    public function clear(mixed $worker, bool $all = false): void;

    /**
     * Clear failed jobs off of the worker
     *
     * @param  mixed $worker
     * @return void
     */
    public function clearFailed(mixed $worker): void;

    /**
     * Flush all jobs off of the worker
     *
     * @param  bool $all
     * @return void
     */
    public function flush(bool $all = false): void;

    /**
     * Flush all failed jobs off of the worker
     *
     * @return void
     */
    public function flushFailed(): void;

    /**
     * Flush all worker items
     *
     * @return void
     */
    public function flushAll(): void;

}
