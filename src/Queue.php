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

use Pop\Queue\Processor\AbstractJob;
use Pop\Queue\Processor\Job;
use Pop\Queue\Processor\Task;

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
     * Worker priority constants
     */
    const FIFO = 'FIFO'; // Same as LILO
    const FILO = 'FILO'; // Same as LIFO

    /**
     * Worker type
     * @var ?string
     */
    protected ?string $priority = 'FIFO';

    /**
     * Constructor
     *
     * Instantiate the worker object
     *
     * @param  string $priority
     */
    public function __construct(string $priority = 'FIFO')
    {
        $this->setPriority($priority);
    }

    /**
     * Create a worker with jobs
     *
     * @param  AbstractJob|array $jobs
     * @param  string            $priority
     * @return Queue
     */
    public static function create(AbstractJob|array $jobs, string $priority = 'FIFO'): Queue
    {
        $queue = new self($priority);

        if (!is_array($jobs)) {
            $jobs = [$jobs];
        }
        foreach ($jobs as $job) {
            if ($job instanceof Task) {
                $queue->addTask($job);
            } else if ($job instanceof Job) {
                $queue->addJob($job);
            }
        }

        return $queue;
    }

    /**
     * Set worker priority
     *
     * @param  string $priority
     * @return Queue
     */
    public function setPriority(string $priority = 'FIFO'): Queue
    {
        if (defined('self::' . $priority)) {
            $this->priority = $priority;
        }
        return $this;
    }

    /**
     * Get worker priority
     *
     * @return string
     */
    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * Is worker fifo
     *
     * @return bool
     */
    public function isFifo(): bool
    {
        return ($this->priority == self::FIFO);
    }

    /**
     * Is worker filo
     *
     * @return bool
     */
    public function isFilo(): bool
    {
        return ($this->priority == self::FILO);
    }

    /**
     * Process next job
     *
     * @param  ?Worker $worker
     * @throws Exception
     * @return mixed
     */
    public function processNext(?Worker $worker = null): mixed
    {
        $nextIndex = $this->getNextIndex();

        if ($this->hasJob($nextIndex)) {
            $scheduleCheck = true;
            $isSubMinute   = false;

            // Check scheduled task
            if ($this->jobs[$nextIndex] instanceof Task) {
                $isSubMinute    = ($this->jobs[$nextIndex]->cron()->hasSeconds());
                $scheduleCheck = $this->jobs[$nextIndex]->cron()->evaluate();
            }

            // If there is a sub-minute scheduled task
            if ($isSubMinute) {
                $timer = 0;
                while ($timer < 60) {
                    if (($this->jobs[$nextIndex]->isValid()) && ($scheduleCheck)) {
                        $this->jobs[$nextIndex]->__wakeup();
                        $this->processJob($nextIndex, $worker);
                    }
                    sleep(1);
                    $scheduleCheck = $this->jobs[$nextIndex]->cron()->evaluate();
                    $timer++;
                }
            // Else, process normal scheduled task
            } else if (($this->jobs[$nextIndex]->isValid()) && ($scheduleCheck)) {
                $this->processJob($nextIndex, $worker);
            }
        }

        return $nextIndex;
    }

    /**
     * Process job
     *
     * @param  int     $nextIndex
     * @param  ?Worker $worker
     * @return void
     */
    public function processJob(int $nextIndex, ?Worker $worker = null): void
    {
        try {
            $application = (($worker !== null) && ($worker->hasApplication() !== null)) ? $worker->application() : null;
            $results = $this->jobs[$nextIndex]->run($application);
            if (!empty($results)) {
                $this->results[$nextIndex] = $results;
            }
            $this->jobs[$nextIndex]->complete();
            $this->completed[$nextIndex] = $this->jobs[$nextIndex];

            if (($worker !== null) && ($this->jobs[$nextIndex]->hasJobId()) &&
                ($worker->adapter()->hasJob($this->jobs[$nextIndex]->getJobId()))) {
                $worker->adapter()->updateJob($this->jobs[$nextIndex]);
            }
        } catch (\Exception $e) {
            $this->jobs[$nextIndex]->failed();
            $this->failed[$nextIndex]           = $this->jobs[$nextIndex];
            $this->failedExceptions[$nextIndex] = $e;
            if (($worker !== null) && ($this->failed[$nextIndex]->hasJobId()) &&
                ($worker->adapter()->hasJob($this->failed[$nextIndex]->getJobId()))) {
                $worker->adapter()->failed($worker->getName(), $this->failed[$nextIndex]->getJobId(), $e);
            }
        }
    }

}