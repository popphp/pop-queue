<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\Task;

/**
 * Adapter abstract class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.0
 */
abstract class AbstractTaskAdapter extends AbstractAdapter implements TaskAdapterInterface
{

    /**
     * Schedule job with queue
     *
     * @param  Task $task
     * @return AbstractTaskAdapter
     */
    abstract public function schedule(Task $task): AbstractTaskAdapter;

    /**
     * Get scheduled tasks
     *
     * @return array
     */
    abstract public function getTasks(): array;

    /**
     * Get scheduled task
     *
     * @param  string $taskId
     * @return ?Task
     */
    abstract public function getTask(string $taskId): ?Task;

    /**
     * Update scheduled task
     *
     * @param  Task $task
     * @return AbstractTaskAdapter
     */
    abstract public function updateTask(Task $task): AbstractTaskAdapter;

    /**
     * Remove scheduled task
     *
     * @param  string $taskId
     * @return AbstractTaskAdapter
     */
    abstract public function removeTask(string $taskId): AbstractTaskAdapter;

    /**
     * Get scheduled tasks count
     *
     * @return int
     */
    abstract public function getTaskCount(): int;

    /**
     * Has scheduled tasks
     *
     * @return bool
     */
    abstract public function hasTasks(): bool;

    /**
     * Clear all scheduled task
     *
     * @return AbstractTaskAdapter
     */
    abstract public function clearTasks(): AbstractTaskAdapter;

}
