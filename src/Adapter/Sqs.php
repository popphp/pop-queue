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

use Aws\Sqs\SqsClient;
use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * SQS adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Sqs extends AbstractAdapter
{

    /**
     * SQS client
     * @var ?SqsClient
     */
    protected ?SqsClient $client = null;


    /**
     * Constructor
     *
     * @param SqsClient $client
     */
    public function __construct(SqsClient $client, ?string $priority = null)
    {
        $this->client = $client;
        parent::__construct($priority);
    }

    /**
     * Get SQS client
     *
     * @return ?SqsClient
     */
    public function getClient(): ?SqsClient
    {
        return $this->client;
    }

    /**
     * Get SQS client (alias)
     *
     * @return ?SqsClient
     */
    public function client(): ?SqsClient
    {
        return $this->client;
    }

    /**
     * Get queue length
     *
     * @return int
     */
    public function getLength(): int
    {

    }

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    public function getStatus(int $index): int
    {

    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return Sqs
     */
    public function push(AbstractJob $job): Sqs
    {
        return $this;
    }

    /**
     * Pop job off of queue
     *
     * @return ?AbstractJob
     */
    public function pop(): ?AbstractJob
    {
        return null;
    }

    /**
     * Schedule job with queue
     *
     * @param  Task $task
     * @return Sqs
     */
    public function schedule(Task $task): Sqs
    {
        return $this;
    }

    /**
     * Get scheduled tasks
     *
     * @return array
     */
    public function getTasks(): array
    {

    }

    /**
     * Get scheduled task
     *
     * @param  string $taskId
     * @return ?Task
     */
    public function getTask(string $taskId): ?Task
    {

    }

    /**
     * Get scheduled tasks count
     *
     * @return int
     */
    public function getTaskCount(): int
    {

    }

}