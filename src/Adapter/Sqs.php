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
namespace Pop\Queue\Adapter;

use Aws\Sqs\SqsClient;
use Pop\Queue\Queue;
use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\PayloadSigner;

/**
 * SQS adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Sqs extends AbstractAdapter
{

    /**
     * SQS client
     * @var ?SqsClient
     */
    protected ?SqsClient $client = null;

    /**
     * Queue URL
     * @var ?string
     */
    protected ?string $queueUrl = null;

    /**
     * Message group ID
     * @var string
     */
    protected string $groupId = 'pop-queue';

    /**
     * In-flight receipt handles, keyed by job ID, populated by reserve()
     * and consumed by delete()/release()/bury()
     * @var array
     */
    protected array $receiptHandles = [];

    /**
     * Constructor
     *
     * @param SqsClient $client
     * @param string    $queueUrl
     * @param string    $groupId
     */
    public function __construct(SqsClient $client, string $queueUrl, string $groupId = 'pop-queue')
    {
        $this->client   = $client;
        $this->queueUrl = $queueUrl;
        $this->groupId  = $groupId;
        $priority       = str_ends_with($queueUrl, '.fifo') ? Queue::FIFO : Queue::FILO;

        parent::__construct($priority);
    }

    /**
     * Create SQS adapter
     *
     * @param  SqsClient $client
     * @param  string $queueUrl
     * @param  string $groupId
     * @return Sqs
     */
    public static function create(SqsClient $client, string $queueUrl, string $groupId = 'pop-queue'): Sqs
    {
        return new self($client, $queueUrl, $groupId);
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
     * Get queue URL
     *
     * @return string
     */
    public function getQueueUrl(): string
    {
        return $this->queueUrl;
    }

    /**
     * Get queue group ID
     *
     * @return string
     */
    public function getGroupId(): string
    {
        return $this->groupId;
    }

    /**
     * Get the count of messages on the queue, both visible (pending) and
     * not visible (reserved/in-flight), per the adapter contract
     *
     * @return int
     */
    public function getEnd(): int
    {
        $result = $this->client->getQueueAttributes([
            'AttributeNames' => [
                'ApproximateNumberOfMessages',
                'ApproximateNumberOfMessagesNotVisible',
                'ApproximateNumberOfMessagesDelayed'
            ],
            'QueueUrl'       => $this->queueUrl
        ]);

        $attributes = $result->get('Attributes');

        return (int)($attributes['ApproximateNumberOfMessages'] ?? 0) +
            (int)($attributes['ApproximateNumberOfMessagesNotVisible'] ?? 0) +
            (int)($attributes['ApproximateNumberOfMessagesDelayed'] ?? 0);
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return Sqs
     */
    public function push(AbstractJob $job): Sqs
    {
        $params = [
            'MessageAttributes' => [
                'Type' => [
                    'DataType'    => 'String',
                    'StringValue' => 'job'
                ],
                'JobId' => [
                    'DataType'    => 'String',
                    'StringValue' => $job->getJobId()
                ]
            ],
            'MessageBody' => base64_encode(PayloadSigner::sign(serialize(clone $job))),
            'QueueUrl'    => $this->queueUrl
        ];

        if ($this->isFifo()) {
            $params['MessageGroupId'] = $this->groupId;
        }

        // Honor the job's delay() via SQS's own initial-delivery delay. This is
        // distinct from the VisibilityTimeout used by reserve(), which only
        // controls redelivery of an already in-flight message. SQS caps
        // DelaySeconds at 900 (15 minutes). AWS does not allow DelaySeconds on
        // individual messages sent to a FIFO queue (it's a queue-level setting
        // there) — sendMessage() rejects the request if it's set, so this is
        // skipped entirely for FIFO queues rather than silently dropped.
        if (!$this->isFifo() && $job->getAvailableAt() !== null) {
            $delaySeconds = min(max(0, $job->getAvailableAt() - time()), 900);
            if ($delaySeconds > 0) {
                $params['DelaySeconds'] = $delaySeconds;
            }
        }

        $this->client->sendMessage($params);

        return $this;
    }

    /**
     * Atomically claim the next eligible job, via SQS's native visibility timeout
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $result = $this->client->receiveMessage([
            'MessageAttributeNames' => ['Type', 'JobId'],
            'MaxNumberOfMessages'   => 1,
            'VisibilityTimeout'     => 60,
            'QueueUrl'              => $this->queueUrl
        ]);

        if (!isset($result->get('Messages')[0]['Body'])) {
            return null;
        }

        $message = $result->get('Messages')[0];
        $raw     = PayloadSigner::verify(base64_decode($message['Body']));
        $job     = ($raw !== false) ? unserialize($raw) : null;

        if ($job instanceof AbstractJob) {
            $this->receiptHandles[$job->getJobId()] = $message['ReceiptHandle'];
        }

        return $job;
    }

    /**
     * Put a job back to pending (delete + re-send; SQS has no in-place requeue)
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return Sqs
     */
    public function release(AbstractJob $job, ?int $delay = null): Sqs
    {
        $this->delete($job);
        $this->push($job);

        return $this;
    }

    /**
     * Permanently remove a job
     *
     * @param  AbstractJob $job
     * @return Sqs
     */
    public function delete(AbstractJob $job): Sqs
    {
        $jobId = $job->getJobId();
        if (isset($this->receiptHandles[$jobId])) {
            $this->client->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $this->receiptHandles[$jobId]
            ]);
            unset($this->receiptHandles[$jobId]);
        }

        return $this;
    }

    /**
     * Stop redelivering a job. SQS cannot durably record a dead-letter reason
     * from this client alone — configure a native SQS redrive policy on a
     * separate dead-letter queue for real dead-letter handling.
     *
     * @param  AbstractJob $job
     * @param  ?string     $reason
     * @return Sqs
     */
    public function bury(AbstractJob $job, ?string $reason = null): Sqs
    {
        $this->delete($job);
        return $this;
    }

    /**
     * Check if adapter has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return ($this->getEnd() > 0);
    }

    /**
     * Count of pending + reserved jobs
     *
     * @return int
     */
    public function count(): int
    {
        return $this->getEnd();
    }

    /**
     * Clear pending and reserved jobs
     *
     * @return Sqs
     */
    public function clear(): Sqs
    {
        $this->client->purgeQueue(['QueueUrl' => $this->queueUrl]);
        $this->receiptHandles = [];

        return $this;
    }

    public function hasDeadJobs(): bool
    {
        return false;
    }

    public function countDead(): int
    {
        return 0;
    }

    public function getDeadJobs(bool $unserialize = true): array
    {
        return [];
    }

    public function getDeadJob(string $jobId, bool $unserialize = true): mixed
    {
        return null;
    }

    /**
     * @throws Exception
     */
    public function retryDeadJob(string $jobId): Sqs
    {
        throw new Exception(
            'Error: The Sqs adapter does not support dead-letter introspection. ' .
            'Configure a native SQS redrive policy on a separate dead-letter queue instead.'
        );
    }

    /**
     * @throws Exception
     */
    public function deleteDeadJob(string $jobId): Sqs
    {
        throw new Exception(
            'Error: The Sqs adapter does not support dead-letter introspection. ' .
            'Configure a native SQS redrive policy on a separate dead-letter queue instead.'
        );
    }

    public function clearDead(): Sqs
    {
        return $this;
    }

}
