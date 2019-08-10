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
     * @var Job\Schedule[]
     */
    protected $schedules = [];

    /**
     * Add job
     *
     * @param  Job\AbstractJob $job
     * @return Job\Schedule
     */
    public function addJob(Job\AbstractJob $job)
    {
        if (!$job->hasProcessor()) {
            $job->setProcessor($this);
        }

        $schedule = new Job\Schedule(($job));

        $this->jobs[]      = $job;
        $this->schedules[] = $schedule;

        return $schedule;
    }

    /**
     * Process next job
     *
     * @return void
     */
    public function processNext()
    {
        foreach ($this->schedules as $key => $schedule) {
            if ($schedule->isDue()) {
                try {
                    $schedule->getJob()->run();
                    $schedule->getJob()->setAsCompleted();
                } catch (\Exception $e) {
                    $schedule->getJob()->setAsFailed();
                    $this->failed[$key]           = $schedule->getJob();
                    $this->failedExceptions[$key] = $e;
                }
            }
        }
    }

}