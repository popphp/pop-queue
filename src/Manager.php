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
namespace Pop\Queue;

use ArrayIterator;
use Pop\Queue\Adapter\AbstractAdapter;

/**
 * Worker manager class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Manager implements \ArrayAccess, \Countable, \IteratorAggregate
{

    /**
     * Workers
     * @var array
     */
    protected array $workers = [];

    /**
     * Constructor
     *
     * Instantiate the worker manager object.
     *
     * @param  mixed $workers
     */
    public function __construct(mixed $workers = null)
    {
        if (!empty($workers)) {
            if (is_array($workers)) {
                $this->addWorkers($workers);
            } else if ($workers instanceof Worker) {
                $this->addWorker($workers);
            }
        }
    }

    /**
     * Create worker manager
     *
     * @param  mixed $workers
     * @return Manager
     */
    public static function create(mixed $workers = null): Manager
    {
        return new self($workers);
    }

    /**
     * Attempt to load pre-existing workers from adapter
     *
     * @param  AbstractAdapter $adapter
     * @return Manager
     */
    public static function load(AbstractAdapter $adapter): Manager
    {
        $workerNames = $adapter->getWorkers();
        $workers     = [];

        foreach ($workerNames as $workerName) {
            $workers[] = new Worker($workerName, $adapter);
        }

        return new self($workers);
    }

    /**
     * Add worker
     *
     * @param  Worker $worker
     * @return Manager
     */
    public function addWorker(Worker $worker): Manager
    {
        $this->workers[$worker->getName()] = $worker;
        return $this;
    }

    /**
     * Add workers
     *
     * @param  array $workers
     * @return Manager
     */
    public function addWorkers(array $workers): Manager
    {
        foreach ($workers as $worker) {
            $this->addWorker($worker);
        }
        return $this;
    }

    /**
     * Get workers
     *
     * @return array
     */
    public function getWorkers(): array
    {
        return $this->workers;
    }

    /**
     * Get worker
     *
     * @param  string $worker
     * @return Worker|null
     */
    public function getWorker(string $worker): Worker|null
    {
        return $this->workers[$worker] ?? null;
    }

    /**
     * Has worker
     *
     * @param  string $worker
     * @return bool
     */
    public function hasWorker(string $worker): bool
    {
        return (isset($this->workers[$worker]));
    }

    /**
     * Register a worker with the manager
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->addWorker($value);
    }

    /**
     * Get a worker
     *
     * @param  string $name
     * @return ?Worker
     */
    public function __get(string $name): ?Worker
    {
        return $this->getWorker($name);
    }

    /**
     * Determine if a worker is registered with the manager object
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->workers[$name]);
    }

    /**
     * Unset a worker with the manager
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        if (isset($this->workers[$name])) {
            unset($this->workers[$name]);
        }
    }

    /**
     * Set a worker with the manager
     *
     * @param  mixed $offset
     * @param  mixed $value
     * @return void
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * Get a worker
     *
     * @param  mixed $offset
     * @return ?Worker
     */
    public function offsetGet(mixed $offset): ?Worker
    {
        return $this->__get($offset);
    }

    /**
     * Determine if a worker is registered with the manager object
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * Unset a worker from the manager
     *
     * @param  string $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->__unset($offset);
    }

    /**
     * Return count
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->workers);
    }

    /**
     * Get iterator
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->workers);
    }

}