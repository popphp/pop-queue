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
     * Add job
     *
     * @param  Job\AbstractJob $job
     * @param  int             $timestamp
     * @return Schedule
     */
    public function addJob(Job\AbstractJob $job, $timestamp)
    {
        if (!$job->hasProcessor()) {
            $job->setProcessor($this);
        }
        $this->jobs[$timestamp] = $job;
        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @return AbstractProcessor
     */
    public function addJobs(array $jobs)
    {
        foreach ($jobs as $timestamp => $job) {
            $this->addJob($job, $timestamp);
        }
        return $this;
    }

    /**
     * Get job
     *
     * @param  int $timestamp
     * @return Job\AbstractJob
     */
    public function getJob($timestamp)
    {
        return (isset($this->jobs[$timestamp])) ? $this->jobs[$timestamp] : null;
    }

    /**
     * Has job
     *
     * @param  int $timestamp
     * @return boolean
     */
    public function hasJob($timestamp)
    {
        return (isset($this->jobs[$timestamp]));
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