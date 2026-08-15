<?php
declare(strict_types=1);
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
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\Task;

/**
 * Adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
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

    /**
     * Atomically claim a task's current due-window. Returns true if this
     * call claimed $taskId for $window (no other live claim for that same
     * window exists); false if another claim for the same window is
     * already live. A claim for a *different* window from what's
     * currently stored always succeeds immediately, regardless of the old
     * claim's expiry - only a same-window re-claim is blocked, and only
     * until the stored claim's TTL elapses.
     *
     * @param  string $taskId
     * @param  string $window
     * @return bool
     */
    public function claimTaskRun(string $taskId, string $window): bool;

}
