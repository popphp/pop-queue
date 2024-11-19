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
 * Adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.0
 */
interface AdapterInterface
{

    /**
     * Set queue priority
     *
     * @param  string $priority
     * @return AdapterInterface
     */
    public function setPriority(string $priority = 'FIFO'): AdapterInterface;

    /**
     * Get queue priority
     *
     * @return string
     */
    public function getPriority(): string;

    /**
     * Is FIFO
     *
     * @return bool
     */
    public function isFifo(): bool;

    /**
     * Is FILO
     *
     * @return bool
     */
    public function isFilo(): bool;

    /**
     * Is LILO (alias to FIFO)
     *
     * @return bool
     */
    public function isLilo(): bool;

    /**
     * Is LIFO (alias to FILO)
     *
     * @return bool
     */
    public function isLifo(): bool;

    /**
     * Get queue start index
     *
     * @return int
     */
    public function getStart(): int;

    /**
     * Get queue end index
     *
     * @return int
     */
    public function getEnd(): int;

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    public function getStatus(int $index): int;

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return AdapterInterface
     */
    public function push(AbstractJob $job): AdapterInterface;

    /**
     * Pop job off of queue
     *
     * @return ?AbstractJob
     */
    public function pop(): ?AbstractJob;

    /**
     * Check if adapter has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool;

    /**
     * Check if adapter has failed job
     *
     * @param  int $index
     * @return bool
     */
    public function hasFailedJob(int $index): bool;

    /**
     * Get failed job
     *
     * @param  int  $index
     * @param  bool $unserialize
     * @return mixed
     */
    public function getFailedJob(int $index, bool $unserialize = true): mixed;

    /**
     * Check if adapter has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool;

    /**
     * Get adapter failed jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getFailedJobs(bool $unserialize = true): array;

    /**
     * Clear failed jobs out of the queue
     *
     * @return AdapterInterface
     */
    public function clearFailed(): AdapterInterface;

    /**
     * Clear jobs out of queue
     *
     * @return AdapterInterface
     */
    public function clear(): AdapterInterface;

}
