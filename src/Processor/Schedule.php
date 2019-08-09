<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor;

use Pop\Queue\Processor\Job;

/**
 * Schedule class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Schedule extends AbstractProcessor
{

    /**
     * Job schedules
     * @var array
     */
    protected $schedules = [];

    /**
     * Add job
     *
     * @param  Job\AbstractJob $job
     * @return Schedule
     */
    public function addJob(Job\AbstractJob $job)
    {
        if (!$job->hasProcessor()) {
            $job->setProcessor($this);
        }

        $this->jobs[] = $job;

        return $this;
    }

    /**
     * Set job schedule to custom cron schedule
     *
     * @param  mixed $cronSchedule
     * @return Schedule
     */
    public function cron($cronSchedule)
    {
        return $this;
    }

    /**
     * Set job schedule to every minute
     *
     * @return Schedule
     */
    public function everyMinute()
    {
        return $this;
    }

    /**
     * Set job schedule to every X minutes
     *
     * @param  mixed $minutes
     * @return Schedule
     */
    public function everyMinutesBy($minutes)
    {
        return $this;
    }

    /**
     * Set job schedule to hourly
     *
     * @param  mixed $minute
     * @return Schedule
     */
    public function hourly($minute = null)
    {
        return $this;
    }

    /**
     * Set job schedule to daily
     *
     * @return Schedule
     */
    public function daily()
    {
        // use func args to get multiple daily scenarios (multiple times, certain times, etc.)
        return $this;
    }

    /**
     * Set job schedule to weekly
     *
     * @param  mixed $day
     * @param  mixed $time
     * @return Schedule
     */
    public function weekly($day = null, $time = null)
    {
        return $this;
    }

    /**
     * Set job schedule to monthly
     *
     * @param  mixed $day
     * @param  mixed $time
     * @return Schedule
     */
    public function monthly($day = null, $time = null)
    {
        return $this;
    }

    /**
     * Set job schedule to quarterly
     *
     * @return Schedule
     */
    public function quarterly()
    {
        return $this;
    }

    /**
     * Set job schedule to yearly
     *
     * @return Schedule
     */
    public function yearly()
    {
        return $this;
    }

    /**
     * Set job timezone
     *
     * @param  string $timezone
     * @return Schedule
     */
    public function timezone($timezone)
    {
        return $this;
    }

    /**
     * Process next job
     *
     * @return boolean
     */
    public function processNext()
    {
        return true;
    }

}