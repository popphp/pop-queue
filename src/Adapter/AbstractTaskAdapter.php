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
 * Adapter abstract class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
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

}