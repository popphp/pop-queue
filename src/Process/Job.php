<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Process;

/**
 * Job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.3
 */
class Job extends AbstractJob
{

    /**
     * Create job
     *
     * @param  mixed   $callable
     * @param  mixed   $params
     * @param  ?string $id
     * @return Job
     */
    public static function create(mixed $callable = null, mixed $params = null, ?string $id = null): Job
    {
        return new self($callable, $params, $id);
    }

    /**
     * Create a job object with an application command
     *
     * @param  string  $command
     * @param  ?string $id
     * @return static
     */
    public static function command(string $command, ?string $id = null): static
    {
        return (new static(null, null, $id))->setCommand($command);
    }

    /**
     * Create a job object with a CLI executable command
     *
     * @param  string  $command
     * @param  ?string $id
     * @return static
     */
    public static function exec(string $command, ?string $id = null): static
    {
        return (new static(null, null, $id))->setExec($command);
    }

}
