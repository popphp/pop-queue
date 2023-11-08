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
        if ($job->isValid()) {
            $params = [
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
            'MaxNumberOfMessages' => 1,
            'QueueUrl'            => $this->queueUrl
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
     * Clear queue
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