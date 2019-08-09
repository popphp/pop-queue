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
     * Add jobs
     *
     * @param  array $jobs
     * @return AbstractProcessor
     */
    public function addJobs(array $jobs);

    /**
     * Get jobs
     *
     * @return array
     */
    public function getJobs();

    /**
     * Get failed jobs
     *
     * @return array
     */
    public function getFailedJobs();

    /**
     * Has failed jobs
     *
     * @return boolean
     */
    public function hasFailedJobs();

    /**
     * Processor next job
     *
     * @return boolean
     */
    public function processNext();

}
