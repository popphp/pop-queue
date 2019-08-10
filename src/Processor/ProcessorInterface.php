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

use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs\AbstractJob;

/**
 * Abstract processor class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
interface ProcessorInterface
{

    /**
     * Set queue
     *
     * @param  Queue $queue
     * @return AbstractProcessor
     */
    public function setQueue(Queue $queue);

    /**
     * Get queue
     *
     * @return Queue
     */
    public function getQueue();

    /**
     * Has queue
     *
     * @return boolean
     */
    public function hasQueue();

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
     * @return boolean
     */
    public function processNext();

}
