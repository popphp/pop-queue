<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor;

use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs\Schedule;
use Pop\Queue\Processor\Jobs\AbstractJob;

/**
 * Scheduler class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2020 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.0.0
 */
class Scheduler extends AbstractProcessor
{

    /**
     * Job schedules
     * @var Schedule[]
     */
    protected $schedules = [];

    /**
     * Add job
     *
     * @param  AbstractJob $job
     * @return Schedule
     */
    public function addJob(AbstractJob $job)
    {
        $schedule          = new Schedule(($job));
        $this->schedules[] = $schedule;

        return $schedule;
    }

    /**
     * Add schedule
     *
     * @param  Schedule $schedule
     * @return Scheduler
     */
    public function addSchedule(Schedule $schedule)
    {
        $this->schedules[] = $schedule;

        return $this;
    }

    /**
     * Add schedules
     *
     * @param  array $schedules
     * @return Scheduler
     */
    public function addSchedules(array $schedules)
    {
        foreach ($schedules as $schedule) {
            $this->addSchedule($schedule);
        }
        return $this;
    }

    /**
     * Get schedules
     *
     * @return array
     */
    public function getSchedules()
    {
        return $this->schedules;
    }

    /**
     * Get schedule
     *
     * @param  int $index
     * @return Schedule
     */
    public function getSchedule($index)
    {
        return (isset($this->schedules[$index])) ? $this->schedules[$index] : null;
    }

    /**
     * Has schedules
     *
     * @return boolean
     */
    public function hasSchedules()
    {
        return (count($this->schedules) > 0);
    }

    /**
     * Has schedule
     *
     * @param  int $index
     * @return boolean
     */
    public function hasSchedule($index)
    {
        return (isset($this->schedules[$index]));
    }

    /**
     * Process next job
     *
     * @param  Queue $queue
     * @return void
     */
    public function processNext(Queue $queue = null)
    {
        foreach ($this->schedules as $key => $schedule) {
            if ($schedule->isDue()) {
                try {
                    $this->results[$key] = $schedule->getJob()->run();
                    $schedule->getJob()->setAsCompleted();
                    $this->completed[$key] = $schedule->getJob();

                    if ((null !== $queue) && ($this->completed[$key]->hasJobId()) &&
                        ($queue->adapter()->hasJob($this->completed[$key]->getJobId()))) {
                        $queue->adapter()->updateJob($this->completed[$key]->getJobId(), false, true);
                        $job = $queue->adapter()->getJob($this->completed[$key]->getJobId());
                        if (($schedule->hasRunUntil()) && ($schedule->isExpired($job['attempts']))) {
                            $queue->adapter()->updateJob($this->completed[$key]->getJobId(), true, false);
                        }
                    }
                } catch (\Exception $e) {
                    $schedule->getJob()->setAsFailed();
                    $this->failed[$key]           = $schedule->getJob();
                    $this->failedExceptions[$key] = $e;
                    if ((null !== $queue) && ($this->failed[$key]->hasJobId()) &&
                        ($queue->adapter()->hasJob($this->failed[$key]->getJobId()))) {
                        $queue->adapter()->failed($queue->getName(), $this->failed[$key]->getJobId(), $e);
                    }
                }
            }
        }
    }

}