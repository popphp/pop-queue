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
 * Abstract queue class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
abstract class AbstractQueue implements QueueInterface
{

    /**
     * Queue name
     * @var string
     */
    protected string $name;

    /**
     * Queue adapter
     * @var AdapterInterface|TaskAdapterInterface
     */
    protected AdapterInterface|TaskAdapterInterface $adapter;

    /**
     * Set name
     *
     * @param  string $name
     * @return AbstractQueue
     */
    public function setName(string $name): AbstractQueue
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Has name
     *
     * @return bool
     */
    public function hasName(): bool
    {
        return $this->name;
    }

    /**
     * Set adapter
     *
     * @param  AdapterInterface|TaskAdapterInterface $adapter
     * @return Queue
     */
    public function setAdapter(AdapterInterface|TaskAdapterInterface $adapter): Queue
    {
        $this->adapter = $adapter;
        return $this;
    }

    /**
     * Get adapter
     *
     * @return AdapterInterface|TaskAdapterInterface
     */
    public function getAdapter(): AdapterInterface|TaskAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Get adapter (alias)
     *
     * @return AdapterInterface|TaskAdapterInterface
     */
    public function adapter(): AdapterInterface|TaskAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * Work next job
     *
     * @param  ?Application $application
     * @return AbstractQueue
     */
    abstract public function work(?Application $application = null): AbstractQueue;

    /**
     * Run schedule
     *
     * @param  ?Application $application
     * @throws Exception|Process\Exception
     * @return AbstractQueue
     */
    abstract public function run(?Application $application = null): AbstractQueue;

    /**
     * Clear queue
     *
     * @return AbstractQueue
     */
    abstract public function clear(): AbstractQueue;

}