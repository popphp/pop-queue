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

/**
 * Queue manager class
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
     * Queues
     * @var array
     */
    protected array $queues = [];

    /**
     * Constructor
     *
     * Instantiate the queue manager object.
     *
     * @param  mixed $queues
     */
    public function __construct(mixed $queues = null)
    {
        if ($queues !== null) {
            if (is_array($queues)) {
                $this->addQueues($queues);
            } else if ($queues instanceof Queue) {
                $this->addQueue($queues);
            }
        }
    }

    /**
     * Create queue manager
     *
     * @param  mixed $queues
     * @return Manager
     */
    public static function create(mixed $queues = null): Manager
    {
        return new self($queues);
    }

    /**
     * Add queue
     *
     * @param  Queue $queue
     * @return Manager
     */
    public function addQueue(Queue $queue): Manager
    {
        $this->queues[$queue->getName()] = $queue;
        return $this;
    }

    /**
     * Add queues
     *
     * @param  array $queues
     * @return Manager
     */
    public function addQueues(array $queues): Manager
    {
        foreach ($queues as $queue) {
            $this->addQueue($queue);
        }
        return $this;
    }

    /**
     * Get queues
     *
     * @return array
     */
    public function getQueues(): array
    {
        return $this->queues;
    }

    /**
     * Get queue
     *
     * @param  string $queue
     * @return Queue|null
     */
    public function getQueue(string $queue): Queue|null
    {
        return $this->queues[$queue] ?? null;
    }

    /**
     * Has queue
     *
     * @param  string $queue
     * @return bool
     */
    public function hasQueue(string $queue): bool
    {
        return (isset($this->queues[$queue]));
    }

    /**
     * Register a queue with the manager
     *
     * @param  string $name
     * @param  mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->addQueue($value);
    }

    /**
     * Get a queue
     *
     * @param  string $name
     * @return ?Queue
     */
    public function __get(string $name): ?Queue
    {
        return $this->getQueue($name);
    }

    /**
     * Determine if a queue is registered with the manager object
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->queues[$name]);
    }

    /**
     * Unset a queue with the manager
     *
     * @param  string $name
     * @return void
     */
    public function __unset(string $name): void
    {
        if (isset($this->queues[$name])) {
            unset($this->queues[$name]);
        }
    }

    /**
     * Set a queue with the manager
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
     * Get a queue
     *
     * @param  mixed $offset
     * @return ?Queue
     */
    public function offsetGet(mixed $offset): ?Queue
    {
        return $this->__get($offset);
    }

    /**
     * Determine if a queue is registered with the manager object
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * Unset a queue from the manager
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
        return count($this->queues);
    }

    /**
     * Get iterator
     *
     * @return ArrayIterator
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->queues);
    }

}