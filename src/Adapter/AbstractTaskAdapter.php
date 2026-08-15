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
 * Adapter abstract class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
abstract class AbstractTaskAdapter extends AbstractAdapter implements TaskAdapterInterface
{

    /**
     * How long, in seconds, a claim blocks a *same-window* re-claim.
     * Shared by every concrete adapter's claimTaskRun() implementation.
     * Not configurable - it has no relationship to any task's cron
     * recurrence interval (the explicit window value each implementation
     * compares against is what makes that safe). It does need to outlast
     * the longest window a claim must survive: a claim is never refreshed
     * or released while its task runs, and a coarse (non-sub-minute) task
     * is due across its entire ~60-second window (evaluate() stays true
     * for the whole minute, not just at :00), so a second worker can
     * legitimately re-evaluate the same coarse task's window many seconds
     * after the first worker claimed it. 90 seconds covers a full
     * 60-second coarse window plus slack, not just one claim-then-execute
     * round trip.
     */
    protected const TASK_CLAIM_TTL = 90;

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

    /**
     * Atomically claim a task's current due-window
     *
     * @param  string $taskId
     * @param  string $window
     * @return bool
     */
    abstract public function claimTaskRun(string $taskId, string $window): bool;

}
