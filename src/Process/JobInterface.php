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
namespace Pop\Queue\Process;

use Pop\Application;

/**
 * Job interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.1.0
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
     * Get job results
     *
     * @return mixed
     */
    public function getResults(): mixed;

    /**
     * Has job results
     *
     * @return bool
     */
    public function hasResults(): bool;

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
     * Set max attempts
     *
     * @param  int $maxAttempts
     * @return JobInterface
     */
    public function setMaxAttempts(int $maxAttempts): JobInterface;

    /**
     * Get max attempts
     *
     * @return int
     */
    public function getMaxAttempts(): int;

    /**
     * Has max attempts
     *
     * @return bool
     */
    public function hasMaxAttempts(): bool;

    /**
     * Is job set for only one max attempt
     *
     * @return bool
     */
    public function isAttemptOnce(): bool;

    /**
     * Get actual attempts
     *
     * @return int
     */
    public function getAttempts(): int;

    /**
     * Has actual attempts
     *
     * @return bool
     */
    public function hasAttempts(): bool;

    /**
     * Set the run until property
     *
     * @param  int|string $runUntil
     * @return JobInterface
     */
    public function runUntil(int|string $runUntil): JobInterface;

    /**
     * Has run until
     *
     * @return bool
     */
    public function hasRunUntil(): bool;

    /**
     * Get run until value
     *
     * @return int|string|null
     */
    public function getRunUntil(): int|string|null;

    /**
     * Determine if the job has expired
     *
     * @return bool
     */
    public function isExpired(): bool;

    /**
     * Determine if the job has exceeded max attempts
     *
     * @return bool
     */
    public function hasExceededMaxAttempts(): bool;

    /**
     * Determine if the job is still valid
     *
     * @return bool
     */
    public function isValid(): bool;

    /**
     * Has job run yet
     *
     * @return bool
     */
    public function hasNotRun(): bool;

    /**
     * Start job
     *
     * @return JobInterface
     */
    public function start(): JobInterface;

    /**
     * Get started timestamp
     *
     * @return ?int
     */
    public function getStarted(): ?int;

    /**
     * Has job started
     *
     * @return bool
     */
    public function hasStarted(): bool;

    /**
     * Is job running and has not completed or failed yet
     *
     * @return bool
     */
    public function isRunning(): bool;

    /**
     * Complete job
     *
     * @return JobInterface
     */
    public function complete(): JobInterface;

    /**
     * Get completed timestamp
     *
     * @return ?int
     */
    public function getCompleted(): ?int;

    /**
     * Is job complete
     *
     * @return bool
     */
    public function isComplete(): bool;

    /**
     * Set job as failed
     *
     * @param  ?string $message
     * @return AbstractJob
     */
    function failed(?string $message = null): JobInterface;

    /**
     * Has job failed
     *
     * @return bool
     */
    public function hasFailed(): bool;

    /**
     * Add failed message
     *
     * @param  string $message
     * @return JobInterface
     */
    public function addFailedMessage(string $message): JobInterface;

    /**
     * Has failed messages
     *
     * @return bool
     */
    public function hasFailedMessages(): bool;

    /**
     * Get failed messages
     *
     * @return array
     */
    public function getFailedMessages(): array;

    /**
     * Get failed timestamp
     *
     * @return ?int
     */
    public function getFailed(): ?int;
    
    /**
     * Run job
     *
     * @param  ?Application $application
     * @return mixed
     */
    public function run(?Application $application = null): mixed;

}
