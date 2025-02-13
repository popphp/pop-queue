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
namespace Pop\Queue;

use ArrayIterator;
use Pop\Application;
use Pop\Queue\Process\AbstractJob;

/**
 * Queue worker class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.2
 */
class Worker implements \ArrayAccess, \Countable, \IteratorAggregate
{

    /**
     * Queues
     * @var array
     */
    protected array $queues = [];

    /**
     * Application object
     * @var ?Application
     */
    protected ?Application $application = null;

    /**
     * Constructor
     *
     * Instantiate the queue worker object.
     *
     * @param mixed $queues
     * @param ?Application $application
     */
    public function __construct(mixed $queues = null, ?Application $application = null)
    {
        if (!empty($queues)) {
            if (is_array($queues)) {
                $this->addQueues($queues);
            } else if ($queues instanceof Queue) {
                $this->addQueue($queues);
            }
        }

        $this->application = $application;
    }

    /**
     * Create queue worker worker
     *
     * @param  mixed $queues
     * @param  ?Application $application
     * @return Worker
     */
    public static function create(mixed $queues = null, ?Application $application = null): Worker
    {
        return new self($queues, $application);
    }

    /**
     * Get the application
     *
     * @return ?Application
     */
    public function getApplication(): ?Application
    {
        return $this->application;
    }

    /**
     * Get the application (alias)
     *
     * @return ?Application
     */
    public function application(): ?Application
    {
        return $this->application;
    }

    /**
     * Has application
     *
     * @return bool
     */
    public function hasApplication(): bool
    {
        return ($this->application !== null);
    }

    /**
     * Add queue
     *
     * @param  Queue $queue
     * @return Worker
     */
    public function addQueue(Queue $queue): Worker
    {
        $this->queues[$queue->getName()] = $queue;
        return $this;
    }

    /**
     * Add queues
     *
     * @param  array $queues
     * @return Worker
     */
    public function addQueues(array $queues): Worker
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
     * @return ?Queue
     */
    public function getQueue(string $queue): ?Queue
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
     * Work next job in queue
     *
     * @param  string $queueName
     * @return ?AbstractJob
     */
    public function work(string $queueName): ?AbstractJob
    {
        $job = null;

        if (isset($this->queues[$queueName])) {
            $job = $this->queues[$queueName]->work($this->application);
        }

        return $job;
    }

    /**
     * Work next job across in all queues
     *
     * @return array
     */
    public function workAll(): array
    {
        $jobs = [];
        foreach ($this->queues as $queueName => $queue) {
            $jobs[$queueName] = $queue->work($this->application);
        }
        return $jobs;
    }

    /**
     * Run next scheduled task in queue
     *
     * @param  string $queueName
     * @return array
     */
    public function run(string $queueName): array
    {
        $tasks = [];
        if (isset($this->queues[$queueName])) {
            $tasks[$queueName] = $this->queues[$queueName]->run($this->application);
        }
        return $tasks;
    }

    /**
     * Run next scheduled task across in all queues
     *
     * @return array
     */
    public function runAll(): array
    {
        $tasks = [];
        foreach ($this->queues as $queueName => $queue) {
            $tasks[$queueName] = $queue->run($this->application);
        }
        return $tasks;
    }

    /**
     * Clear jobs from queue
     *
     * @param  string $queueName
     * @return Worker
     */
    public function clear(string $queueName): Worker
    {
        if (isset($this->queues[$queueName])) {
            $this->queues[$queueName]->clear();
        }
        return $this;
    }

    /**
     * Clear failed jobs from queue
     *
     * @param  string $queueName
     * @return Worker
     */
    public function clearFailed(string $queueName): Worker
    {
        if (isset($this->queues[$queueName])) {
            $this->queues[$queueName]->clearFailed();
        }
        return $this;
    }

    /**
     * Clear tasks from queue
     *
     * @param  string $queueName
     * @return Worker
     */
    public function clearTasks(string $queueName): Worker
    {
        if (isset($this->queues[$queueName])) {
            $this->queues[$queueName]->clearTasks();
        }
        return $this;
    }

    /**
     * Clear all jobs from queues
     *
     * @return Worker
     */
    public function clearAll(): Worker
    {
        foreach ($this->queues as $queue) {
            $queue->clear();
        }
        return $this;
    }

    /**
     * Clear all failed jobs from queues
     *
     * @return Worker
     */
    public function clearAllFailed(): Worker
    {
        foreach ($this->queues as $queue) {
            $queue->clearFailed();
        }
        return $this;
    }

    /**
     * Clear all tasks from queues
     *
     * @return Worker
     */
    public function clearAllTasks(): Worker
    {
        foreach ($this->queues as $queue) {
            $queue->clearTasks();
        }
        return $this;
    }

    /**
     * Register a queue with the worker
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
     * Determine if a queue is registered with the worker object
     *
     * @param  string $name
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->queues[$name]);
    }

    /**
     * Unset a queue with the worker
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
     * Set a queue with the worker
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
     * Determine if a queue is registered with the worker object
     *
     * @param  mixed $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->__isset($offset);
    }

    /**
     * Unset a queue from the worker
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
