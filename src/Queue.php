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

use Pop\Application;
use Pop\Queue\Adapter\AdapterInterface;
use Pop\Queue\Adapter\TaskAdapterInterface;
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
class Queue extends AbstractQueue
{

    /**
     * Queue priority constants
     */
    const FIFO = 'FIFO'; // Same as LILO
    const FILO = 'FILO'; // Same as LIFO

    /**
     * Constructor
     *
     * Instantiate the queue object
     *
     * @param string $name
     * @param AdapterInterface|TaskAdapterInterface $adapter
     * @param ?string $priority
     */
    public function __construct(string $name, AdapterInterface|TaskAdapterInterface $adapter, ?string $priority = null)
    {
        $this->setName($name);
        $this->setAdapter($adapter);
        if ($priority !== null) {
            $this->setPriority($priority);
        }
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
    public function addJob(AbstractJob $job, ?int $maxAttempts = null): Queue
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
    public function addJobs(array $jobs, ?int $maxAttempts = null): Queue
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
     * @throws Exception
     * @return Queue
     */
    public function addTask(Task $task, ?int $maxAttempts = null): Queue
    {
        if (!($this->adapter instanceof TaskAdapterInterface)) {
            throw new Exception('Error: That queue adapter does not support scheduled tasks');
        }
        if ($maxAttempts !== null) {
            $task->setMaxAttempts($maxAttempts);
        }

        $this->adapter->schedule($task);

        return $this;
    }

    /**
     * Add tasks
     *
     * @param  array $tasks
     * @param  ?int  $maxAttempts
     * @throws Exception
     * @return Queue
     */
    public function addTasks(array $tasks, ?int $maxAttempts = null): Queue
    {
        foreach ($tasks as $task) {
            $this->addTask($task, $maxAttempts);
        }
        return $this;
    }

    /**
     * Clear queue
     *
     * @return Queue
     */
    public function clear(): Queue
    {
        $this->adapter->clear();
        return $this;
    }

    /**
     * Work next job
     *
     * @param  ?Application $application
     * @return Queue
     */
    public function work(?Application $application = null): Queue
    {
        $job = $this->adapter->pop();

        if (($job instanceof AbstractJob) && ($job->isValid())) {
            try {
                $job->run($application);
                $job->complete();
            } catch (\Exception $e) {
                $job->failed($e->getMessage());
                $this->adapter->push($job);
            }
        }

        return $this;
    }

    /**
     * Run schedule
     *
     * @param  ?Application $application
     * @throws Process\Exception
     * @return Queue
     */
    public function run(?Application $application = null): Queue
    {
        if (($this->adapter instanceof TaskAdapterInterface) && ($this->adapter->hasTasks())) {
            $taskIds = $this->adapter->getTasks();
            foreach ($taskIds as $taskId) {
                $task = $this->adapter->getTask($taskId);
                if ($task instanceof Task) {
                    $isSubMinute   = ($task->cron()->hasSeconds());
                    $scheduleCheck = $task->cron()->evaluate();
                    if ($isSubMinute) {
                        $timer = 0;
                        while ($timer < 60) {
                            if (($task->isValid()) && ($scheduleCheck)) {
                                $task->__wakeup();
                                try {
                                    $task->run($application);
                                    $task->complete();
                                } catch (\Exception $e) {
                                    $task->failed($e->getMessage());
                                    $this->adapter->removeTask($taskId);
                                    $this->adapter->schedule($task);
                                }
                            }
                            sleep(1);
                            $scheduleCheck = $task->cron()->evaluate();
                            $timer++;
                        }
                    } else if (($task->isValid()) && ($scheduleCheck)) {
                        try {
                            $task->run($application);
                            $task->complete();
                            $this->adapter->updateTask($task);
                        } catch (\Exception $e) {
                            $task->failed($e->getMessage());
                            $this->adapter->updateTask($task);
                        }
                    }
                }
            }
        }

        return $this;
    }

}
