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
namespace Pop\Queue;

use Pop\Application;
use Pop\Queue\Adapter\AdapterInterface;
use Pop\Queue\Adapter\TaskAdapterInterface;
use Pop\Queue\Process\AbstractJob;

/**
 * Queue interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.3
 */
interface QueueInterface
{

    /**
     * Set name
     *
     * @param  string $name
     * @return QueueInterface
     */
    public function setName(string $name): QueueInterface;

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Has name
     *
     * @return bool
     */
    public function hasName(): bool;

    /**
     * Set adapter
     *
     * @param  AdapterInterface|TaskAdapterInterface $adapter
     * @return QueueInterface
     */
    public function setAdapter(AdapterInterface|TaskAdapterInterface $adapter): QueueInterface;

    /**
     * Get adapter
     *
     * @return AdapterInterface|TaskAdapterInterface
     */
    public function getAdapter(): AdapterInterface|TaskAdapterInterface;

    /**
     * Get adapter (alias)
     *
     * @return AdapterInterface|TaskAdapterInterface
     */
    public function adapter(): AdapterInterface|TaskAdapterInterface;

    /**
     * Work next job
     *
     * @param  ?Application $application
     * @return ?AbstractJob
     */
    public function work(?Application $application = null): ?AbstractJob;

    /**
     * Run schedule
     *
     * @param  ?Application $application
     * @throws Exception|Process\Exception
     * @return array
     */
    public function run(?Application $application = null): array;

    /**
     * Clear queue
     *
     * @return QueueInterface
     */
    public function clear(): QueueInterface;

}
