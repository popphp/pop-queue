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
     * Process next job
     *
     * @return boolean
     */
    public function processNext()
    {
        return true;
    }

}