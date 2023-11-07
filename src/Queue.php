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

use Pop\Queue\Adapter\AdapterInterface;
use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * Queue class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Queue
{

    /**
     * Queue priority constants
     */
    const FIFO = 'FIFO'; // Same as LILO
    const FILO = 'FILO'; // Same as LIFO

    /**
     * Queue name
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Queue adapter
     * @var ?AdapterInterface
     */
    protected ?AdapterInterface $adapter = null;

    /**
     * Constructor
     *
     * Instantiate the queue object
     *
     * @param string           $name
     * @param AdapterInterface $adapter
     * @param ?string          $priority
     */
    public function __construct(string $name, AdapterInterface $adapter, ?string $priority = null)
    {
        $this->setName($name);
        $this->setAdapter($adapter);
        if ($priority !== null) {
            $this->setPriority($priority);
        }
    }

    /**
     * Set name
     *
     * @param  string $name
     * @return Queue
     */
    public function setName(string $name): Queue
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set adapter
     *
     * @param  AdapterInterface $adapter
     * @return Queue
     */
    public function setAdapter(AdapterInterface $adapter): Queue
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Get adapter
     *
     * @return AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get adapter (alias)
     *
     * @return AdapterInterface
     */
    public function adapter(): AdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Set queue priority
     *
     * @param  string $priority
     * @return Queue
     */
    public function setPriority(string $priority = 'FIFO'): Queue
    {
        $this->adapter->setPriority($priority);
        return $this;
    }

    /**
     * Get queue priority
     *
     * @return string
     */
    public function getPriority(): string
    {
        return $this->adapter->getPriority();
    }

    /**
     * Is FIFO
     *
     * @return bool
     */
    public function isFifo(): bool
    {
        return $this->adapter->isFifo();
    }

    /**
     * Is FILO
     *
     * @return bool
     */
    public function isFilo(): bool
    {
        return $this->adapter->isFilo();
    }

    /**
     * Is LILO (alias to FIFO)
     *
     * @return bool
     */
    public function isLilo(): bool
    {
        return $this->adapter->isLilo();
    }

    /**
     * Is LIFO (alias to FILO)
     *
     * @return bool
     */
    public function isLifo(): bool
    {
        return $this->adapter->isLifo();
    }

    /**
     * Add job
     *
     * @param  AbstractJob $job
     * @param  ?int        $maxAttempts
     * @return Queue
     */
    public function addJob(AbstractJob $job, ?int $maxAttempts = 1): Queue
    {
        if ($maxAttempts !== null) {
            $job->setMaxAttempts($maxAttempts);
        }
        $this->adapter->push($job);

        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @param  ?int  $maxAttempts
     * @return Queue
     */
    public function addJobs(array $jobs, ?int $maxAttempts = 1): Queue
    {
        foreach ($jobs as $job) {
            $this->addJob($job, $maxAttempts);
        }
        return $this;
    }

    /**
     * Add task (alias)
     *
     * @param  Task $task
     * @param  ?int $maxAttempts
     * @return Queue
     */
    public function addTask(Task $task, ?int $maxAttempts = 0): Queue
    {
        return $this->addJob($task, $maxAttempts);
    }

    /**
     * Add tasks
     *
     * @param  array $tasks
     * @param  ?int  $maxAttempts
     * @return Queue
     */
    public function addTasks(array $tasks, ?int $maxAttempts = 0): Queue
    {
        foreach ($tasks as $task) {
            $this->addTask($task, $maxAttempts);
        }
        return $this;
    }
    
}
