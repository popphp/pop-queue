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
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\Task;

/**
 * Adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.3
 */
interface TaskAdapterInterface
{

    /**
     * Schedule job with queue
     *
     * @param  Task $task
     * @return TaskAdapterInterface
     */
    public function schedule(Task $task): TaskAdapterInterface;

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
     * Update scheduled task
     *
     * @param  Task $task
     * @return TaskAdapterInterface
     */
    public function updateTask(Task $task): TaskAdapterInterface;

    /**
     * Remove scheduled task
     *
     * @param  string $taskId
     * @return TaskAdapterInterface
     */
    public function removeTask(string $taskId): TaskAdapterInterface;

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

    /**
     * Clear all scheduled task
     *
     * @return TaskAdapterInterface
     */
    public function clearTasks(): TaskAdapterInterface;

}
