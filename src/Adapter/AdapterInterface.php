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
namespace Pop\Queue\Adapter;

use Pop\Queue\Processor\Jobs\AbstractJob;
use Pop\Queue\Queue;

/**
 * Queue adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
interface AdapterInterface
{

    /**
     * Get queue object
     *
     * @param  string $queueName
     * @return Queue
     */
    public function loadQueue($queueName);

    /**
     * Check if queue adapter has jobs
     *
     * @param  string $queueName
     * @return boolean
     */
    public function hasJobs($queueName);

    /**
     * Get queue jobs
     *
     * @param  string $queueName
     * @return array
     */
    public function getJobs($queueName);

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  string $queueName
     * @return boolean
     */
    public function hasFailedJobs($queueName);

    /**
     * Get queue jobs
     *
     * @param  string $queueName
     * @return array
     */
    public function getFailedJobs($queueName);

    /**
     * Push job onto queue stack
     *
     * @param  string $queueName
     * @param  mixed  $job
     * @return void
     */
    public function push($queueName, $job);

    /**
     * Pop job off of queue stack
     *
     * @return void
     */
    public function pop();

}
