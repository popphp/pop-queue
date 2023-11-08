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

use Pop\Queue\Queue;
use Pop\Queue\Process\Task;
use Pop\Queue\Process\AbstractJob;

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
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Queue type
     * @var string
     */
    protected string $priority = 'FIFO';

    /**
     * Constructor
     *
     * Instantiate the adapter object
     *
     * @param ?string $priority
     */
    public function __construct(?string $priority = null)
    {
        if ($priority !== null) {
            $this->setPriority($priority);
        }
    }

    /**
     * Set queue priority
     *
     * @param  string $priority
     * @return AbstractAdapter
     */
    public function setPriority(string $priority = 'FIFO'): AbstractAdapter
    {
        if (defined('Pop\Queue\Queue::' . $priority)) {
            $this->priority = $priority;
        }
        return $this;
    }

    /**
     * Get queue priority
     *
     * @return string
     */
    public function getPriority(): string
    {
        return $this->priority;
    }

    /**
     * Is FIFO
     *
     * @return bool
     */
    public function isFifo(): bool
    {
        return ($this->priority == Queue::FIFO);
    }

    /**
     * Is FILO
     *
     * @return bool
     */
    public function isFilo(): bool
    {
        return ($this->priority == Queue::FILO);
    }

    /**
     * Is LILO (alias to FIFO)
     *
     * @return bool
     */
    public function isLilo(): bool
    {
        return ($this->priority == Queue::FIFO);
    }

    /**
     * Is LIFO (alias to FILO)
     *
     * @return bool
     */
    public function isLifo(): bool
    {
        return ($this->priority == Queue::FILO);
    }

    /**
     * Get queue start index
     *
     * @return int
     */
    abstract public function getStart(): int;

    /**
     * Get queue end index
     *
     * @return int
     */
    abstract public function getEnd(): int;

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    abstract public function getStatus(int $index): int;

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return AdapterInterface
     */
    abstract public function push(AbstractJob $job): AdapterInterface;

    /**
     * Pop job off of queue
     *
     * @return ?AbstractJob
     */
    abstract public function pop(): ?AbstractJob;

    /**
     * Clear queue
     *
     * @return AdapterInterface
     */
    abstract public function clear(): AdapterInterface;

}