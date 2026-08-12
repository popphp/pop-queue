<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\AbstractJob;

/**
 * Adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
interface AdapterInterface
{

    public function setPriority(string $priority = 'FIFO'): AdapterInterface;

    public function getPriority(): string;

    public function isFifo(): bool;

    public function isFilo(): bool;

    public function isLilo(): bool;

    public function isLifo(): bool;

    /**
     * Push a job onto the queue
     *
     * @param  AbstractJob $job
     * @return AdapterInterface
     */
    public function push(AbstractJob $job): AdapterInterface;

    /**
     * Atomically claim the next eligible job and lease it. Returns null if
     * nothing is eligible (no pending jobs due, or all reserved jobs have a
     * live lease).
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob;

    /**
     * Put a reserved job back to pending. $delay overrides the job's own
     * backoff schedule when given; otherwise release() computes the delay
     * from $job->getBackoffDelay().
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return AdapterInterface
     */
    public function release(AbstractJob $job, ?int $delay = null): AdapterInterface;

    /**
     * Permanently remove a job (success/ack)
     *
     * @param  AbstractJob $job
     * @return AdapterInterface
     */
    public function delete(AbstractJob $job): AdapterInterface;

    /**
     * Move a job to the dead-letter store (terminal)
     *
     * @param  AbstractJob $job
     * @param  ?string     $reason
     * @return AdapterInterface
     */
    public function bury(AbstractJob $job, ?string $reason = null): AdapterInterface;

    /**
     * Whether there are pending or reserved jobs
     *
     * @return bool
     */
    public function hasJobs(): bool;

    /**
     * Count of pending + reserved jobs
     *
     * @return int
     */
    public function count(): int;

    /**
     * Clear pending and reserved jobs (not dead-letter jobs)
     *
     * @return AdapterInterface
     */
    public function clear(): AdapterInterface;

    public function hasDeadJobs(): bool;

    public function countDead(): int;

    /**
     * @param  bool $unserialize
     * @return array
     */
    public function getDeadJobs(bool $unserialize = true): array;

    /**
     * @param  string $jobId
     * @param  bool   $unserialize
     * @return mixed
     */
    public function getDeadJob(string $jobId, bool $unserialize = true): mixed;

    /**
     * Move a dead-letter job back to pending
     *
     * @param  string $jobId
     * @return AdapterInterface
     */
    public function retryDeadJob(string $jobId): AdapterInterface;

    public function deleteDeadJob(string $jobId): AdapterInterface;

    public function clearDead(): AdapterInterface;

}
