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

/**
 * Job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
interface JobInterface
{


    /**
     * Set job status
     *
     * @param  int $status
     * @return JobInterface
     */
    public function setStatus($status);

    /**
     * Get job status
     *
     * @return int
     */
    public function getStatus();

    /**
     * Is job open
     *
     * @return boolean
     */
    public function isOpen();

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
     * Start job
     *
     * @return void
     */
    public function start();

    /**
     * Run job
     *
     * @return void
     */
    public function run();

    /**
     * Stop job
     *
     * @return void
     */
    public function stop();

}