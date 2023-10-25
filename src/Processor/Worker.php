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
namespace Pop\Queue\Processor;

use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs\AbstractJob;

/**
 * Worker class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Worker extends AbstractProcessor
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
     * Worker jobs
     * @var array
     */
    protected array $jobs = [];

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
     * Set worker priority
     *
     * @param  string $priority
     * @return Worker
     */
    public function setPriority(string $priority = 'FIFO'): Worker
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
     * Add job
     *
     * @param  AbstractJob $job
     * @return Worker
     */
    public function addJob(AbstractJob $job): Worker
    {
        if ($this->isFilo()) {
            array_unshift($this->jobs, $job);
        } else {
            $this->jobs[] = $job;
        }
        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @return Worker
     */
    public function addJobs(array $jobs): Worker
    {
        foreach ($jobs as $job) {
            $this->addJob($job);
        }
        return $this;
    }

    /**
     * Get jobs
     *
     * @return array
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }

    /**
     * Get job
     *
     * @param  int $index
     * @return AbstractJob|null
     */
    public function getJob(int $index): AbstractJob|null
    {
        return $this->jobs[$index] ?? null;
    }

    /**
     * Has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return (count($this->jobs) > 0);
    }

    /**
     * Has job
     *
     * @param  int $index
     * @return bool
     */
    public function hasJob(int $index): bool
    {
        return (isset($this->jobs[$index]));
    }

    /**
     * Has next job
     *
     * @return bool
     */
    public function hasNextJob(): bool
    {
        $current = key($this->jobs);
        return (($current !== null) && ($current < count($this->jobs)));
    }

    /**
     * Process next job
     *
     * @param  ?Queue $queue
     * @return mixed
     */
    public function processNext(?Queue $queue = null): mixed
    {
        $nextIndex = $this->getNextIndex();

        if ($this->hasJob($nextIndex)) {
            try {
                $application = (($queue !== null) && ($queue->hasApplication() !== null)) ? $queue->application() : null;
                $this->results[$nextIndex] = $this->jobs[$nextIndex]->run($application);
                $this->jobs[$nextIndex]->setAsCompleted();
                $this->completed[$nextIndex] = $this->jobs[$nextIndex];

                if (($queue !== null) && ($this->jobs[$nextIndex]->hasJobId()) &&
                    ($queue->adapter()->hasJob($this->jobs[$nextIndex]->getJobId()))) {
                    $queue->adapter()->updateJob($this->jobs[$nextIndex]->getJobId(), true, true);
                }
            } catch (\Exception $e) {
                $this->jobs[$nextIndex]->setAsFailed();
                $this->failed[$nextIndex]           = $this->jobs[$nextIndex];
                $this->failedExceptions[$nextIndex] = $e;
                if (($queue !== null) && ($this->failed[$nextIndex]->hasJobId()) &&
                    ($queue->adapter()->hasJob($this->failed[$nextIndex]->getJobId()))) {
                    $queue->adapter()->failed($queue->getName(), $this->failed[$nextIndex]->getJobId(), $e);
                }
            }
        }

        return $nextIndex;
    }

    /**
     * Get next index
     *
     * @return int
     */
    public function getNextIndex(): int
    {
        $index = key($this->jobs);
        next($this->jobs);
        return $index;
    }

}