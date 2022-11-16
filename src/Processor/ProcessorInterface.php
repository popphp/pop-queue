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
namespace Pop\Queue\Processor;

use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs\AbstractJob;

/**
 * Abstract processor class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
 */
interface ProcessorInterface
{

    /**
     * Get job results
     *
     * @return array
     */
    public function getJobResults();

    /**
     * Get job result
     *
     * @param  mixed $index
     * @return mixed
     */
    public function getJobResult($index);

    /**
     * Has job results
     *
     * @return boolean
     */
    public function hasJobResults();

    /**
     * Get completed jobs
     *
     * @return array
     */
    public function getCompletedJobs();

    /**
     * Get completed job
     *
     * @param  mixed $index
     * @return AbstractJob
     */
    public function getCompletedJob($index);

    /**
     * Has completed jobs
     *
     * @return boolean
     */
    public function hasCompletedJobs();

    /**
     * Get failed jobs
     *
     * @return array
     */
    public function getFailedJobs();

    /**
     * Get failed job
     *
     * @param  mixed $index
     * @return AbstractJob
     */
    public function getFailedJob($index);

    /**
     * Has failed jobs
     *
     * @return boolean
     */
    public function hasFailedJobs();

    /**
     * Get failed exceptions
     *
     * @return array
     */
    public function getFailedExceptions();

    /**
     * Get failed exception
     *
     * @param  mixed $index
     * @return AbstractJob
     */
    public function getFailedException($index);

    /**
     * Has failed exceptions
     *
     * @return boolean
     */
    public function hasFailedExceptions();

    /**
     * Processor next job
     *
     * @param  Queue $queue
     * @return void
     */
    public function processNext(Queue $queue = null);

}
