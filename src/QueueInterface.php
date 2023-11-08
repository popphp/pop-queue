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
namespace Pop\Queue;

use Pop\Application;
use Pop\Queue\Adapter\AdapterInterface;
use Pop\Queue\Adapter\TaskAdapterInterface;

/**
 * Queue interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
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
     * Clear queue
     *
     * @return QueueInterface
     */
    public function clear(): QueueInterface;

    /**
     * Work next job
     *
     * @param  ?Application $application
     * @return QueueInterface
     */
    public function work(?Application $application = null): QueueInterface;

    /**
     * Run schedule
     *
     * @param  ?Application $application
     * @throws Exception|Process\Exception
     * @return QueueInterface
     */
    public function run(?Application $application = null): QueueInterface;

}