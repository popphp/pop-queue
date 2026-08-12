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
 * Registry interface
 *
 * A keyed store of WorkerRecords. Each worker writes only its own record,
 * so implementations need no locking, lease or atomic-claim protocol -
 * this is deliberately a much simpler contract than AdapterInterface.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
interface RegistryInterface
{

    /**
     * Store a record, replacing any existing record with the same ID
     *
     * @param  WorkerRecord $record
     * @return void
     */
    public function write(WorkerRecord $record): void;

    /**
     * Fetch a record by ID, or null if it isn't there
     *
     * @param  string $id
     * @return ?WorkerRecord
     */
    public function read(string $id): ?WorkerRecord;

    /**
     * Fetch every stored record
     *
     * @return array
     */
    public function all(): array;

    /**
     * Remove a record. A no-op if the ID isn't there.
     *
     * @param  string $id
     * @return void
     */
    public function delete(string $id): void;

    /**
     * Remove every record whose heartbeat is older than the given number of
     * seconds, returning how many were removed
     *
     * @param  int $olderThanSeconds
     * @return int
     */
    public function prune(int $olderThanSeconds): int;

}
