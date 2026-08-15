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

use Pop\Queue\Queue;
use Pop\Queue\Process\AbstractJob;

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
abstract class AbstractAdapter implements AdapterInterface
{

    /**
     * Queue priority
     * @var string
     */
    protected string $priority = 'FIFO';

    /**
     * Constructor
     *
     * @param ?string $priority
     */
    public function __construct(?string $priority = null)
    {
        if ($priority !== null) {
            $this->setPriority($priority);
        }
    }

    public function setPriority(string $priority = 'FIFO'): AbstractAdapter
    {
        if (defined('Pop\Queue\Queue::' . $priority)) {
            $this->priority = $priority;
        }
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function isFifo(): bool
    {
        return ($this->priority == Queue::FIFO);
    }

    public function isFilo(): bool
    {
        return ($this->priority == Queue::FILO);
    }

    public function isLilo(): bool
    {
        return ($this->priority == Queue::FIFO);
    }

    public function isLifo(): bool
    {
        return ($this->priority == Queue::FILO);
    }

    abstract public function push(AbstractJob $job): AdapterInterface;

    abstract public function reserve(): ?AbstractJob;

    abstract public function release(AbstractJob $job, ?int $delay = null): AdapterInterface;

    abstract public function delete(AbstractJob $job): AdapterInterface;

    abstract public function bury(AbstractJob $job, ?string $reason = null): AdapterInterface;

    abstract public function hasJobs(): bool;

    abstract public function count(): int;

    abstract public function clear(): AdapterInterface;

    abstract public function hasDeadJobs(): bool;

    abstract public function countDead(): int;

    abstract public function getDeadJobs(bool $unserialize = true): array;

    abstract public function getDeadJob(string $jobId, bool $unserialize = true): mixed;

    abstract public function retryDeadJob(string $jobId): AdapterInterface;

    abstract public function deleteDeadJob(string $jobId): AdapterInterface;

    abstract public function clearDead(): AdapterInterface;

}
