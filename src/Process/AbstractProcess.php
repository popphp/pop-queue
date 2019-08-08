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

/**
 * Abstract process class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
abstract class AbstractProcess implements ProcessInterface
{

    /**
     * Worker jobs
     * @var array
     */
    protected $jobs = [];

    /**
     * Add job
     *
     * @param  AbstractJob $job
     * @return AbstractProcess
     */
    public function addJob(AbstractJob $job)
    {
        $this->jobs[] = $job;
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
        foreach ($jobs as $job) {
            $this->addJob($job);
        }
        return $this;
    }

}
