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
use Pop\Queue\Queue;
use Pop\Queue\Process\AbstractJob;

/**
 * SQS adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.1.0
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
     * Get queue start index
     *
     * @return int
     */
    public function getStart(): int
    {
        return 0;
    }

    /**
     * Get queue end index
     *
     * @return int
     */
    public function getEnd(): int
    {
        $result = $this->client->getQueueAttributes([
            'AttributeNames' => ['ApproximateNumberOfMessages'],
            'QueueUrl'       => $this->queueUrl
        ]);

        return (int)$result->get('Attributes')['ApproximateNumberOfMessages'];
    }

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    public function getStatus(int $index): int
    {
        return 0;
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return Sqs
     */
    public function push(AbstractJob $job): Sqs
    {
        $status = ($job->hasFailed()) ? '2' : '1';
        if ($job->isValid()) {
            $params = [
                'MessageAttributes' => [
                    'Type' => [
                        'DataType'    => 'String',
                        'StringValue' => 'job'
                    ],
                    'Status' => [
                        'DataType'    => 'Number',
                        'StringValue' => $status
                    ]
                ],
                'MessageBody' => base64_encode(serialize(clone $job)),
                'QueueUrl'    => $this->queueUrl
            ];

            if ($this->isFifo()) {
                $params['MessageGroupId'] = $this->groupId;
            }

            $this->client->sendMessage($params);
        }

        return $this;
    }

    /**
     * Pop job off of queue
     *
     * @return ?AbstractJob
     */
    public function pop(): ?AbstractJob
    {
        $job    = false;
        $params = [
            'MessageAttributeNames' => ['Type', 'Status'],
            'MaxNumberOfMessages'   => 1,
            'QueueUrl'              => $this->queueUrl
        ];

        $result = $this->client->receiveMessage($params);

        if (isset($result->get('Messages')[0]['Body'])) {
            $job = $result->get('Messages')[0]['Body'];
            $this->client->deleteMessage([
                'QueueUrl'      => $this->queueUrl,
                'ReceiptHandle' => $result->get('Messages')[0]['ReceiptHandle']
            ]);
        }

        return ($job !== false) ? unserialize(base64_decode($job)) : null;
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
     * Check if adapter has failed job
     *
     * @param  mixed $index
     * @return bool
     */
    public function hasFailedJob(mixed $index): bool
    {
        $failed = $this->getFailedJobs();
        return isset($failed[$index]);
    }

    /**
     * Get failed job
     *
     * @param  mixed $index
     * @param  bool  $unserialize
     * @return mixed
     */
    public function getFailedJob(mixed $index, bool $unserialize = true): mixed
    {
        $failed = $this->getFailedJobs($unserialize);
        return $failed[$index] ?? null;
    }

    /**
     * Check if adapter has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool
    {
        $failed = false;
        $params = [
            'MessageAttributeNames' => ['Type', 'Status'],
            'MaxNumberOfMessages'   => 1,
            'QueueUrl'              => $this->queueUrl
        ];

        $result = $this->client->receiveMessage($params);

        while (isset($result->get('Messages')[0])) {
            $message = $result->get('Messages')[0];
            if (isset($message['MessageAttributes']['Status']) &&
                ($message['MessageAttributes']['Status']['StringValue'] == 2)) {
                $failed = true;
                break;
            }
            $result = $this->client->receiveMessage($params);
        }

        return $failed;
    }

    /**
     * Get adapter failed jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getFailedJobs(bool $unserialize = true): array
    {
        $failed = [];
        $params = [
            'MessageAttributeNames' => ['Type', 'Status'],
            'MaxNumberOfMessages'   => 1,
            'QueueUrl'              => $this->queueUrl
        ];

        $result = $this->client->receiveMessage($params);

        while (isset($result->get('Messages')[0])) {
            $message = $result->get('Messages')[0];
            if (isset($message['MessageAttributes']['Status']) &&
                ($message['MessageAttributes']['Status']['StringValue'] == 2)) {
                if ($unserialize) {
                    $message['Body'] = unserialize(base64_decode($message['Body']));
                }

                $failed[$message['MessageId']] = $message;
            }
            $result = $this->client->receiveMessage($params);
        }

        return $failed;
    }

    /**
     * Clear failed jobs out of the queue
     *
     * @return Sqs
     */
    public function clearFailed(): Sqs
    {
        $params = [
            'MessageAttributeNames' => ['Type', 'Status'],
            'MaxNumberOfMessages'   => $this->getEnd(),
            'QueueUrl'              => $this->queueUrl
        ];

        $result = $this->client->receiveMessage($params);

        foreach ($result->get('Messages') as $message) {
            if (isset($message['MessageAttributes']['Status']) &&
                ($message['MessageAttributes']['Status']['StringValue'] == 2)) {
                $this->client->deleteMessage([
                    'QueueUrl'      => $this->queueUrl,
                    'ReceiptHandle' => $message['ReceiptHandle']
                ]);
            }
        }

        return $this;
    }

    /**
     * Clear jobs out of queue
     *
     * @return Sqs
     */
    public function clear(): Sqs
    {
        $this->client->purgeQueue([
            'QueueUrl' => $this->queueUrl
        ]);
        return $this;
    }

}
