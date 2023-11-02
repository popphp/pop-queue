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
namespace Pop\Queue\Processor\Jobs;

use Pop\Queue\Processor\AbstractProcessor;

/**
 * Job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface JobInterface
{

    /**
     * Generate job ID
     *
     * @return string
     */
    public function generateJobId(): string;

    /**
     * Set job ID
     *
     * @param  string $id
     * @return JobInterface
     */
    public function setJobId(string $id): JobInterface;

    /**
     * Get job ID
     *
     * @return ?string
     */
    public function getJobId(): ?string;

    /**
     * Has job ID
     *
     * @return bool
     */
    public function hasJobId(): bool;

    /**
     * Set job description
     *
     * @param  string $description
     * @return JobInterface
     */
    public function setJobDescription(string $description): JobInterface;

    /**
     * Get job description
     *
     * @return ?string
     */
    public function getJobDescription(): ?string;

    /**
     * Has job description
     *
     * @return bool
     */
    public function hasJobDescription(): bool;

    /**
     * Set job callable
     *
     * @param  mixed $callable
     * @param  mixed $params
     * @return JobInterface
     */
    public function setCallable(mixed $callable, mixed $params = null): JobInterface;

    /**
     * Set job application command
     *
     * @param  string $command
     * @return JobInterface
     */
    public function setCommand(string $command): JobInterface;

    /**
     * Set job CLI executable command
     *
     * @param  string $command
     * @return JobInterface
     */
    public function setExec(string $command): JobInterface;

    /**
     * Get job callable
     *
     * @return mixed
     */
    public function getCallable(): mixed;

    /**
     * Get job application command
     *
     * @return ?string
     */
    public function getCommand(): ?string;

    /**
     * Get job CLI executable command
     *
     * @return ?string
     */
    public function getExec(): ?string;

    /**
     * Has job callable
     *
     * @return bool
     */
    public function hasCallable(): bool;

    /**
     * Has job application command
     *
     * @return bool
     */
    public function hasCommand(): bool;

    /**
     * Has job CLI executable command
     *
     * @return bool
     */
    public function hasExec(): bool;
    /**
     * Set job to only attempt once
     *
     * @param  bool $attemptOnce
     * @return JobInterface
     */
    public function attemptOnce(bool $attemptOnce = true): JobInterface;

    /**
     * Set job to only attempt to run once
     *
     * @return bool
     */
    public function isAttemptOnce(): bool;

    /**
     * Set job as failed
     *
     * @return JobInterface
     */
    public function setAsFailed(): JobInterface;

    /**
     * Has job failed
     *
     * @return bool
     */
    public function hasFailed(): bool;

    /**
     * Get failed timestamp
     *
     * @return ?int
     */
    public function getFailedTimestamp(): ?int;

    /**
     * Is job running
     *
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Is job complete
     *
     * @return bool
     */
    public function isComplete(): bool;

    /**
     * Get completed timestamp
     *
     * @return ?int
     */
    public function getCompletedTimestamp(): ?int;

    /**
     * Run job
     *
     * @return mixed
     */
    public function run(): mixed;
}