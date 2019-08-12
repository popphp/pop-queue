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
 * Queue adapter abstract class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Get queue object
     *
     * @param  string $queueName
     * @return Queue
     */
    abstract public function loadQueue($queueName);

    /**
     * Check if queue adapter has jobs
     *
     * @param  string $queueName
     * @return boolean
     */
    abstract public function hasJobs($queueName);

    /**
     * Get queue jobs
     *
     * @param  string $queueName
     * @return array
     */
    abstract public function getJobs($queueName);

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  string $queueName
     * @return boolean
     */
    abstract public function hasFailedJobs($queueName);

    /**
     * Get queue jobs
     *
     * @param  string $queueName
     * @return array
     */
    abstract public function getFailedJobs($queueName);

    /**
     * Push job onto queue stack
     *
     * @param  string $queueName
     * @param  mixed  $job
     * @return void
     */
    abstract public function push($queueName, $job);

    /**
     * Pop job off of queue stack
     *
     * @return void
     */
    abstract public function pop();

}
