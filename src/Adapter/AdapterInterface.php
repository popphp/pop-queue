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

use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * Adapter interface
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
interface AdapterInterface
{

    /**
     * Set queue priority
     *
     * @param  string $priority
     * @return AdapterInterface
     */
    public function setPriority(string $priority = 'FIFO'): AdapterInterface;

    /**
     * Get queue priority
     *
     * @return string
     */
    public function getPriority(): string;

    /**
     * Is FIFO
     *
     * @return bool
     */
    public function isFifo(): bool;

    /**
     * Is FILO
     *
     * @return bool
     */
    public function isFilo(): bool;

    /**
     * Is LILO (alias to FIFO)
     *
     * @return bool
     */
    public function isLilo(): bool;

    /**
     * Is LIFO (alias to FILO)
     *
     * @return bool
     */
    public function isLifo(): bool;

    /**
     * Get queue start index
     *
     * @return int
     */
    public function getStart(): int;

    /**
     * Get queue end index
     *
     * @return int
     */
    public function getEnd(): int;

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    public function getStatus(int $index): int;

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return AdapterInterface
     */
    public function push(AbstractJob $job): AdapterInterface;

    /**
     * Pop job off of queue
     *
     * @return ?AbstractJob
     */
    public function pop(): ?AbstractJob;

}