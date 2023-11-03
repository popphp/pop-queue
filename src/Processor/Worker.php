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
     * Process next job
     *
     * @param  ?Queue $queue
     * @throws Exception
     * @return mixed
     */
    public function processNext(?Queue $queue = null): mixed
    {
        $nextIndex = $this->getNextIndex();

        if ($this->hasJob($nextIndex)) {
            $scheduleCheck = true;
            $hasSeconds    = false;

            // Check scheduled task
            if ($this->jobs[$nextIndex] instanceof Task) {
                $hasSeconds    = ($this->jobs[$nextIndex]->cron()->hasSeconds());
                $scheduleCheck = $this->jobs[$nextIndex]->cron()->evaluate();
            }

            // If there is a sub-minute scheduled task
            if ($hasSeconds) {
                $timer = 0;
                while ($timer < 60) {
                    if (($this->jobs[$nextIndex]->isValid()) && ($scheduleCheck)) {
                        $this->jobs[$nextIndex]->__wakeup();
                        $this->processJob($nextIndex, $queue);
                    }
                    sleep(1);
                    $scheduleCheck = $this->jobs[$nextIndex]->cron()->evaluate();
                    $timer++;
                }
            // Else, process normal scheduled task
            } else if (($this->jobs[$nextIndex]->isValid()) && ($scheduleCheck)) {
                $this->processJob($nextIndex, $queue);
            }
        }

        return $nextIndex;
    }

    /**
     * Process job
     *
     * @param  int    $nextIndex
     * @param  ?Queue $queue
     * @return void
     */
    public function processJob(int $nextIndex, ?Queue $queue = null): void
    {
        try {
            $application = (($queue !== null) && ($queue->hasApplication() !== null)) ? $queue->application() : null;
            $this->results[$nextIndex] = $this->jobs[$nextIndex]->run($application);
            $this->jobs[$nextIndex]->complete();
            $this->completed[$nextIndex] = $this->jobs[$nextIndex];

            if (($queue !== null) && ($this->jobs[$nextIndex]->hasJobId()) &&
                ($queue->adapter()->hasJob($this->jobs[$nextIndex]->getJobId()))) {
                $queue->adapter()->updateJob($this->jobs[$nextIndex]);
            }
        } catch (\Exception $e) {
            $this->jobs[$nextIndex]->failed();
            $this->failed[$nextIndex]           = $this->jobs[$nextIndex];
            $this->failedExceptions[$nextIndex] = $e;
            if (($queue !== null) && ($this->failed[$nextIndex]->hasJobId()) &&
                ($queue->adapter()->hasJob($this->failed[$nextIndex]->getJobId()))) {
                $queue->adapter()->failed($queue->getName(), $this->failed[$nextIndex]->getJobId(), $e);
            }
        }
    }

}