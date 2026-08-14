<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
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
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
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
     * Create a job object with an application command - an invocation string
     * routed through the application (e.g. Job::command('greet Nick')), or an
     * argv-style array of already split segments (e.g.
     * Job::command(['notify', 'Hello there, world'])). The router splits the
     * string form on whitespace, so the array form is the only way to pass an
     * argument value that itself contains spaces.
     *
     * @param  string|array $command
     * @param  ?string      $id
     * @return static
     */
    public static function command(string|array $command, ?string $id = null): static
    {
        return (new static(null, null, $id))->setCommand($command);
    }

    /**
     * Create a job object with a CLI executable command - a shell command
     * string (runs via the shell, e.g. Job::exec('ls -la | wc -l')), or an
     * argv-style array to run with no shell involved at all (e.g.
     * Job::exec(['ls', '-la']) - the safer form when any part of the
     * command isn't a fully-trusted literal, since shell metacharacters in
     * an argv element are inert)
     *
     * @param  string|array $command
     * @param  ?string      $id
     * @return static
     */
    public static function exec(string|array $command, ?string $id = null): static
    {
        return (new static(null, null, $id))->setExec($command);
    }

}
