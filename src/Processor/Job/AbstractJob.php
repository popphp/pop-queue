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
namespace Pop\Queue\Processor\Job;

use Pop\Queue\Processor\AbstractProcessor;

/**
 * Abstract job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
abstract class AbstractJob implements JobInterface
{

    /**
     * The processor the job belongs to (Worker or Schedule)
     * @var AbstractProcessor
     */
    protected $processor = null;

    /**
     * Job status (0 - opened, 1 - running, 2 - complete)
     * @var int
     */
    protected $status = 0;

    /**
     * Constructor
     *
     * Instantiate the job object
     *
     * @param  AbstractProcessor $processor
     */
    public function __construct(AbstractProcessor $processor = null)
    {
        if (null !== $processor) {
            $this->setProcessor($processor);
        }
    }

    /**
     * Set processor
     *
     * @param  AbstractProcessor $processor
     * @return AbstractJob
     */
    public function setProcessor(AbstractProcessor $processor)
    {
        $this->processor = $processor;
        return $this;
    }

    /**
     * Get processor
     *
     * @return AbstractProcessor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Set job status
     *
     * @param  int $status
     * @return AbstractJob
     */
    public function setStatus($status)
    {
        $status = (int)$status;
        if (($status >= 0) && ($status <= 2)) {
            $this->status = $status;
        }

        return $this;
    }

    /**
     * Get job status
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Is job open
     *
     * @return boolean
     */
    public function isOpen()
    {
        return ($this->status == 0);
    }

    /**
     * Is job running
     *
     * @return boolean
     */
    public function isRunning()
    {
        return ($this->status == 1);
    }

    /**
     * Is job complete
     *
     * @return boolean
     */
    public function isComplete()
    {
        return ($this->status == 2);
    }

    /**
     * Start job
     *
     * @return void
     */
    abstract public function start();

    /**
     * Run job
     *
     * @return void
     */
    abstract public function run();

    /**
     * Stop job
     *
     * @return void
     */
    abstract public function stop();

}