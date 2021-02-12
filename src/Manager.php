<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue;

/**
 * Queue manager class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
 */
class Manager implements \ArrayAccess, \Countable, \IteratorAggregate
{

    /**
     * Queues
     * @var Queue[]
     */
    protected $queues = [];

    /**
     * Constructor
     *
     * Instantiate the queue manager object.
     *
     * @param  mixed $queues
     */
    public function __construct($queues = null)
    {
        if (null !== $queues) {
            if (is_array($queues)) {
                $this->addQueues($queues);
            } else if ($queues instanceof Queue) {
                $this->addQueue($queues);
            }
        }
    }

    /**
     * Add queue
     *
     * @param  Queue $queue
     * @return Manager
     */
    public function addQueue(Queue $queue)
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
    public function addQueues(array $queues)
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
    public function getQueues()
    {
        return $this->queues;
    }

    /**
     * Get queue
     *
     * @param  string $queue
     * @return Queue
     */
    public function getQueue($queue)
    {
        return (isset($this->queues[$queue])) ? $this->queues[$queue] : null;
    }

    /**
     * Has queue
     *
     * @param  string $queue
     * @return boolean
     */
    public function hasQueue($queue)
    {
        return (isset($this->queues[$queue]));
    }

    /**
     * Register a queue with the manager
     *
     * @param  string $name
     * @param  mixed $value
     * @return Manager
     */
    public function __set($name, $value)
    {
        $this->addQueue($value);
        return $this;
    }

    /**
     * Get a queue
     *
     * @param  string $name
     * @return Queue
     */
    public function __get($name)
    {
        return $this->getQueue($name);
    }

    /**
     * Determine if a queue is registered with the manager object
     *
     * @param  string $name
     * @return boolean
     */
    public function __isset($name)
    {
        return isset($this->queues[$name]);
    }

    /**
     * Unset a queue with the manager
     *
     * @param  string $name
     * @return Manager
     */
    public function __unset($name)
    {
        if (isset($this->queues[$name])) {
            unset($this->queues[$name]);
        }
        return $this;
    }

    /**
     * Set a queue with the manager
     *
     * @param  string $offset
     * @param  mixed $value
     * @return Manager
     */
    public function offsetSet($offset, $value)
    {
        return $this->__set($offset, $value);
    }

    /**
     * Get a queue
     *
     * @param  string $offset
     * @return Queue
     */
    public function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    /**
     * Determine if a queue is registered with the manager object
     *
     * @param  string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return $this->__isset($offset);
    }

    /**
     * Unset a queue from the manager
     *
     * @param  string $offset
     * @return Manager
     */
    public function offsetUnset($offset)
    {
        return $this->__unset($offset);
    }

    /**
     * Return count
     *
     * @return int
     */
    public function count()
    {
        return count($this->queues);
    }

    /**
     * Get iterator
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->queues);
    }

}