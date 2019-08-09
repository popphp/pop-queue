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
namespace Pop\Queue\Process;

use Pop\Queue\Process\Job;

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
class Schedule extends AbstractProcess
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
        $this->jobs[$timestamp] = $job;
        return $this;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @return AbstractProcess
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

}