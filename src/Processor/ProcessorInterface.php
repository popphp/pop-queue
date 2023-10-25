<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
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
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface ProcessorInterface
{

    /**
     * Get job results
     *
     * @return array
     */
    public function getJobResults(): array;

    /**
     * Get job result
     *
     * @param  mixed $index
     * @return mixed
     */
    public function getJobResult(mixed $index): mixed;

    /**
     * Has job results
     *
     * @return bool
     */
    public function hasJobResults(): bool;

    /**
     * Get completed jobs
     *
     * @return array
     */
    public function getCompletedJobs(): array;

    /**
     * Get completed job
     *
     * @param  mixed $index
     * @return AbstractJob|null
     */
    public function getCompletedJob(mixed $index): AbstractJob|null;

    /**
     * Has completed jobs
     *
     * @return bool
     */
    public function hasCompletedJobs(): bool;

    /**
     * Get failed jobs
     *
     * @return array
     */
    public function getFailedJobs(): array;

    /**
     * Get failed job
     *
     * @param  mixed $index
     * @return AbstractJob|null
     */
    public function getFailedJob(mixed $index): AbstractJob|null;

    /**
     * Has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool;

    /**
     * Get failed exceptions
     *
     * @return array
     */
    public function getFailedExceptions(): array;

    /**
     * Get failed exception
     *
     * @param  mixed $index
     * @return \Exception|null
     */
    public function getFailedException(mixed $index): \Exception|null;

    /**
     * Has failed exceptions
     *
     * @return bool
     */
    public function hasFailedExceptions(): bool;

    /**
     * Processor next job
     *
     * @param  ?Queue $queue
     * @return mixed
     */
    public function processNext(?Queue $queue = null): mixed;

}
