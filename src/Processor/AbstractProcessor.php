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
 * Abstract process class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
abstract class AbstractProcessor implements ProcessorInterface
{

    /**
     * The queue the processor belongs to
     * @var Queue
     */
    protected $queue = null;

    /**
     * Worker jobs
     * @var array
     */
    protected $jobs = [];

    /**
     * Failed jobs
     * @var array
     */
    protected $failed = [];

    /**
     * Constructor
     *
     * Instantiate the processor object
     *
     * @param  Queue $queue
     */
    public function __construct(Queue $queue = null)
    {
        if (null !== $queue) {
            $this->setQueue($queue);
        }
    }

    /**
     * Set queue
     *
     * @param  Queue $queue
     * @return AbstractProcessor
     */
    public function setQueue(Queue $queue)
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Get queue
     *
     * @return Queue
     */
    public function getQueue()
    {
        return $this->queue;
    }

    /**
     * Add jobs
     *
     * @param  array $jobs
     * @return AbstractProcessor
     */
    abstract public function addJobs(array $jobs);

    /**
     * Get jobs
     *
     * @return array
     */
    public function getJobs()
    {
        return $this->jobs;
    }

    /**
     * Get failed jobs
     *
     * @return array
     */
    public function getFailedJobs()
    {
        return $this->failed;
    }

    /**
     * Has failed jobs
     *
     * @return boolean
     */
    public function hasFailedJobs()
    {
        return !empty($this->failed);
    }

    /**
     * Process next job
     *
     * @return boolean
     */
    abstract public function processNext();

}
