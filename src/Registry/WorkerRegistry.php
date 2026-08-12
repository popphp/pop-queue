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
namespace Pop\Queue\Registry;

/**
 * Worker registry class
 *
 * The read-side facade over a registry backend: who is running, which of
 * them have gone quiet, and which look genuinely stuck.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class WorkerRegistry
{

    /**
     * Registry backend
     * @var RegistryInterface
     */
    protected RegistryInterface $registry;

    /**
     * Constructor
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Get the underlying backend
     *
     * @return RegistryInterface
     */
    public function getRegistry(): RegistryInterface
    {
        return $this->registry;
    }

    /**
     * Every registered worker, keyed by worker ID
     *
     * @return array
     */
    public function getWorkers(): array
    {
        return $this->registry->all();
    }

    /**
     * A single worker by ID, or null
     *
     * @param  string $id
     * @return ?WorkerRecord
     */
    public function getWorker(string $id): ?WorkerRecord
    {
        return $this->registry->read($id);
    }

    /**
     * How many workers are registered
     *
     * @return int
     */
    public function countWorkers(): int
    {
        return count($this->registry->all());
    }

    /**
     * Workers whose heartbeat has gone quiet. Note this includes workers
     * that are merely busy inside a long job - a worker executing a job
     * cannot heartbeat, so use getStuckWorkers() to narrow to the ones
     * that look genuinely wedged.
     *
     * @param  int $seconds
     * @return array
     */
    public function getStaleWorkers(int $seconds = 90): array
    {
        return array_filter($this->registry->all(), function($record) use ($seconds) {
            return $record->isStale($seconds);
        });
    }

    /**
     * Workers that are stale AND holding a job that has outlived its own
     * timeout - the alerting signal
     *
     * @param  int $seconds
     * @return array
     */
    public function getStuckWorkers(int $seconds = 90): array
    {
        return array_filter($this->registry->all(), function($record) use ($seconds) {
            return $record->isLikelyStuck($seconds);
        });
    }

    /**
     * Remove records left behind by processes that are long gone
     *
     * @param  int $olderThanSeconds
     * @return int
     */
    public function prune(int $olderThanSeconds = 3600): int
    {
        return $this->registry->prune($olderThanSeconds);
    }

}
