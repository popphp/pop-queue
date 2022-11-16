<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor\Jobs;

/**
 * Job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2023 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
 */
class Job extends AbstractJob
{

    /**
     * Create a job object with an application command
     *
     * @param  string $command
     * @param  string $id
     * @return AbstractJob
     */
    public static function command($command, $id = null)
    {
        return (new static(null, null, $id))->setCommand($command);
    }

    /**
     * Create a job object with a CLI executable command
     *
     * @param  string $command
     * @param  string $id
     * @return AbstractJob
     */
    public static function exec($command, $id = null)
    {
        return (new static(null, null, $id))->setExec($command);
    }

}