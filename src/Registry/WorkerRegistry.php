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
     * This process's own record, once registered. A WorkerRegistry instance
     * represents this process's view of the registry, which is what lets the
     * event listeners attached by attachTo() mutate the record by closing
     * over $this.
     * @var ?WorkerRecord
     */
    protected ?WorkerRecord $record = null;

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
     * Register this process, writing its record to the backend
     *
     * @param  ?string $name       optional operator-facing label
     * @param  array   $queueNames names of the queues being serviced
     * @param  string  $mode       WorkerRecord::MODE_DAEMON or MODE_SINGLE_PASS
     * @return WorkerRecord
     */
    public function register(?string $name = null, array $queueNames = [], string $mode = WorkerRecord::MODE_SINGLE_PASS): WorkerRecord
    {
        $this->record = WorkerRecord::create($name, $queueNames, $mode);
        $this->registry->write($this->record);

        return $this->record;
    }

    /**
     * Whether this process has registered
     *
     * @return bool
     */
    public function isRegistered(): bool
    {
        return ($this->record !== null);
    }

    /**
     * This process's own record, or null if it hasn't registered
     *
     * @return ?WorkerRecord
     */
    public function getRecord(): ?WorkerRecord
    {
        return $this->record;
    }

    /**
     * Refresh this process's heartbeat and flush its record. A no-op when
     * not registered, so callers never need to guard.
     *
     * @return void
     */
    public function heartbeat(): void
    {
        if ($this->record === null) {
            return;
        }

        $this->record->touch();
        $this->registry->write($this->record);
    }

    /**
     * Remove this process's record. A no-op when not registered.
     *
     * @return void
     */
    public function deregister(): void
    {
        if ($this->record === null) {
            return;
        }

        $this->registry->delete($this->record->getId());
        $this->record = null;
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
