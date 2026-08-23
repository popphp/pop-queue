<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Registry\Adapter;

use Pop\Queue\Registry\AbstractRegistry;
use Pop\Queue\Registry\WorkerRecord;

/**
 * In-memory registry class
 *
 * A hermetic, single-process reference implementation of the registry
 * contract. Because it never leaves the process it can't provide real
 * cross-process visibility - it exists to prove the contract in tests and
 * to serve as a fake for consumers testing against this package.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Memory extends AbstractRegistry
{

    /**
     * Records, keyed by worker ID
     * @var array
     */
    protected array $records = [];

    public function write(WorkerRecord $record): void
    {
        // Store the flattened form so callers can't mutate what's "stored"
        // by holding on to the object they passed in.
        $this->records[$record->getId()] = $record->toArray();
    }

    public function read(string $id): ?WorkerRecord
    {
        return isset($this->records[$id]) ? WorkerRecord::fromArray($this->records[$id]) : null;
    }

    public function all(): array
    {
        $records = [];
        foreach ($this->records as $id => $data) {
            $records[$id] = WorkerRecord::fromArray($data);
        }

        return $records;
    }

    public function delete(string $id): void
    {
        unset($this->records[$id]);
    }

}
