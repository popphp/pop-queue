<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor\Jobs;

use Pop\Queue\Processor\AbstractProcessor;

/**
 * Job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
 */
interface JobInterface
{

    /**
     * Generate job ID
     *
     * @return string
     */
    public function generateJobId();

    /**
     * Set job ID
     *
     * @param  string $id
     * @return JobInterface
     */
    public function setJobId($id);

    /**
     * Get job ID
     *
     * @return string
     */
    public function getJobId();

    /**
     * Has job ID
     *
     * @return boolean
     */
    public function hasJobId();

    /**
     * Set job description
     *
     * @param  string $description
     * @return JobInterface
     */
    public function setJobDescription($description);

    /**
     * Get job description
     *
     * @return string
     */
    public function getJobDescription();

    /**
     * Has job description
     *
     * @return boolean
     */
    public function hasJobDescription();

    /**
     * Set job callable
     *
     * @param  mixed $callable
     * @param  mixed $params
     * @return JobInterface
     */
    public function setCallable($callable, $params = null);

    /**
     * Set job application command
     *
     * @param  string $command
     * @return JobInterface
     */
    public function setCommand($command);

    /**
     * Set job CLI executable command
     *
     * @param  string executable
     * @return JobInterface
     */
    public function setExec($command);

    /**
     * Get job callable
     *
     * @return mixed
     */
    public function getCallable();

    /**
     * Get job application command
     *
     * @return string
     */
    public function getCommand();

    /**
     * Get job CLI executable command
     *
     * @return string
     */
    public function getExec();

    /**
     * Has job callable
     *
     * @return boolean
     */
    public function hasCallable();

    /**
     * Has job application command
     *
     * @return boolean
     */
    public function hasCommand();

    /**
     * Has job CLI executable command
     *
     * @return boolean
     */
    public function hasExec();
    /**
     * Set job to only attempt once
     *
     * @param  boolean $attemptOnce
     * @return JobInterface
     */
    public function attemptOnce($attemptOnce = true);

    /**
     * Set job to only attempt to run once
     *
     * @return boolean
     */
    public function isAttemptOnce();

    /**
     * Set job as failed
     *
     * @return JobInterface
     */
    public function setAsFailed();

    /**
     * Has job failed
     *
     * @return boolean
     */
    public function hasFailed();

    /**
     * Is job running
     *
     * @return boolean
     */
    public function isRunning();

    /**
     * Is job complete
     *
     * @return boolean
     */
    public function isComplete();

    /**
     * Run job
     *
     * @return void
     */
    public function run();
}