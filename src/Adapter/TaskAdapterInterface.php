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
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\Task;

/**
 * Adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface TaskAdapterInterface
{

    /**
     * Schedule job with queue
     *
     * @param  Task $task
     * @return AdapterInterface
     */
    public function schedule(Task $task): AdapterInterface;

    /**
     * Get scheduled tasks
     *
     * @return array
     */
    public function getTasks(): array;

    /**
     * Get scheduled task
     *
     * @param  string $taskId
     * @return ?Task
     */
    public function getTask(string $taskId): ?Task;

    /**
     * Get scheduled tasks count
     *
     * @return int
     */
    public function getTaskCount(): int;

    /**
     * Has scheduled tasks
     *
     * @return bool
     */
    public function hasTasks(): bool;

}