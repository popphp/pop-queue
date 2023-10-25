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

use Pop\Queue\Processor\Jobs\AbstractJob;
use Pop\Queue\Queue;

/**
 * Queue adapter interface
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
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasJob(mixed $jobId): bool;

    /**
     * Get job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getJob(mixed $jobId, bool $unserialize = true): array;

    /**
     * Update job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  mixed $completed
     * @param  mixed $increment
     * @return void
     */
    public function updateJob(mixed $jobId, mixed $completed = false, mixed $increment = false): void;

    /**
     * Check if queue adapter has jobs
     *
     * @param  mixed $queue
     * @return bool
     */
    public function hasJobs(mixed $queue): bool;

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @param  bool  $unserialize
     * @return array
     */
    public function getJobs(mixed $queue, bool $unserialize = true): array;

    /**
     * Check if queue stack has completed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasCompletedJob(mixed $jobId): bool;

    /**
     * Check if queue adapter has completed jobs
     *
     * @param  mixed $queue
     * @return bool
     */
    public function hasCompletedJobs(mixed $queue): bool;

    /**
     * Get queue completed jobs
     *
     * @param  mixed $queue
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJobs(mixed $queue, bool $unserialize = true): array;

    /**
     * Get queue completed job
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJob(mixed $jobId, bool $unserialize = true): array;

    /**
     * Check if queue stack has failed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasFailedJob(mixed $jobId): bool;

    /**
     * Get failed job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJob(mixed $jobId, bool $unserialize = true): array;

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  mixed $queue
     * @return bool
     */
    public function hasFailedJobs(mixed $queue): bool;

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJobs(mixed $queue, bool $unserialize = true): array;

    /**
     * Push job onto queue stack
     *
     * @param  mixed $queue
     * @param  mixed $job
     * @param  mixed $priority
     * @return string
     */
    public function push(mixed $queue, mixed $job, mixed $priority = null): string;

    /**
     * Move failed job to failed queue stack
     *
     * @param  mixed           $queue
     * @param  mixed           $failedJob
     * @param  \Exception|null $exception
     * @return void
     */
    public function failed(mixed $queue, mixed $failedJob, \Exception|null $exception = null): void;

    /**
     * Pop job off of queue stack
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop(mixed $jobId): void;

    /**
     * Clear completed jobs off of the queue stack
     *
     * @param  mixed $queue
     * @param  bool  $all
     * @return void
     */
    public function clear(mixed $queue, bool $all = false): void;

    /**
     * Clear failed jobs off of the queue stack
     *
     * @param  mixed $queue
     * @return void
     */
    public function clearFailed(mixed $queue): void;

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  bool $all
     * @return void
     */
    public function flush(bool $all = false): void;

    /**
     * Flush all failed jobs off of the queue stack
     *
     * @return void
     */
    public function flushFailed(): void;

    /**
     * Flush all pop queue items
     *
     * @return void
     */
    public function flushAll(): void;

}
