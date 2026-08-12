<?php
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
namespace Pop\Queue\Registry;

/**
 * Worker record class
 *
 * A point-in-time snapshot of one worker process: who it is, what it is
 * servicing, what it is working on right now, and when it was last heard
 * from. Deliberately plain scalars and arrays only, so it serializes as
 * JSON and the registry carries no unserialize() object-injection surface.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class WorkerRecord
{

    /**
     * Worker modes
     */
    const MODE_DAEMON      = 'daemon';
    const MODE_SINGLE_PASS = 'single-pass';

    /**
     * Unique worker instance ID
     * @var string
     */
    protected string $id;

    /**
     * Optional operator-facing label
     * @var ?string
     */
    protected ?string $name = null;

    /**
     * Hostname
     * @var string
     */
    protected string $host;

    /**
     * Process ID
     * @var int
     */
    protected int $pid;

    /**
     * When this worker registered
     * @var int
     */
    protected int $startedAt;

    /**
     * Last heartbeat
     * @var int
     */
    protected int $lastSeenAt;

    /**
     * Names of the queues being serviced
     * @var array
     */
    protected array $queues = [];

    /**
     * Worker mode - MODE_DAEMON or MODE_SINGLE_PASS
     * @var string
     */
    protected string $mode = self::MODE_SINGLE_PASS;

    /**
     * ID of the job currently being executed, if any
     * @var ?string
     */
    protected ?string $currentJobId = null;

    /**
     * Name of the queue the current job came from
     * @var ?string
     */
    protected ?string $currentQueue = null;

    /**
     * When the current job started
     * @var ?int
     */
    protected ?int $currentJobStartedAt = null;

    /**
     * The current job's own timeout, stored so isLikelyStuck() can be
     * answered without loading the job payload
     * @var ?int
     */
    protected ?int $currentJobTimeout = null;

    /**
     * Jobs completed since registration
     * @var int
     */
    protected int $jobsProcessed = 0;

    /**
     * Jobs failed since registration
     * @var int
     */
    protected int $jobsFailed = 0;

    /**
     * Constructor
     *
     * Timestamps are explicit parameters rather than defaulted to time()
     * so time-dependent behavior is testable without sleeping.
     *
     * @param string $id
     * @param string $host
     * @param int    $pid
     * @param int    $startedAt
     * @param int    $lastSeenAt
     */
    public function __construct(string $id, string $host, int $pid, int $startedAt, int $lastSeenAt)
    {
        $this->id         = $id;
        $this->host       = $host;
        $this->pid        = $pid;
        $this->startedAt  = $startedAt;
        $this->lastSeenAt = $lastSeenAt;
    }

    /**
     * Create a record for the current process
     *
     * The ID carries a random suffix so a recycled PID can never collide
     * with an older record from the same host.
     *
     * @param  ?string $name
     * @param  array   $queues
     * @param  string  $mode
     * @return WorkerRecord
     */
    public static function create(?string $name = null, array $queues = [], string $mode = self::MODE_SINGLE_PASS): WorkerRecord
    {
        $host = (gethostname() !== false) ? gethostname() : 'unknown';
        $pid  = getmypid();
        $now  = time();
        $id   = $host . ':' . $pid . ':' . bin2hex(random_bytes(6));

        $record = new self($id, $host, $pid, $now, $now);
        $record->setName($name);
        $record->setQueues($queues);
        $record->setMode($mode);

        return $record;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setName(?string $name): WorkerRecord
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function getStartedAt(): int
    {
        return $this->startedAt;
    }

    public function getLastSeenAt(): int
    {
        return $this->lastSeenAt;
    }

    public function setQueues(array $queues): WorkerRecord
    {
        $this->queues = $queues;
        return $this;
    }

    public function getQueues(): array
    {
        return $this->queues;
    }

    public function setMode(string $mode): WorkerRecord
    {
        $this->mode = $mode;
        return $this;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    /**
     * Refresh the heartbeat
     *
     * @return WorkerRecord
     */
    public function touch(): WorkerRecord
    {
        $this->lastSeenAt = time();
        return $this;
    }

    /**
     * Record the job about to be executed
     *
     * @param  string  $jobId
     * @param  ?string $queue
     * @param  ?int    $timeout
     * @return WorkerRecord
     */
    public function setCurrentJob(string $jobId, ?string $queue = null, ?int $timeout = null): WorkerRecord
    {
        $this->currentJobId        = $jobId;
        $this->currentQueue        = $queue;
        $this->currentJobTimeout   = $timeout;
        $this->currentJobStartedAt = time();
        return $this;
    }

    /**
     * Set the current job's start time directly (used when restoring a
     * record, and by tests that need a deterministic duration)
     *
     * @param  ?int $startedAt
     * @return WorkerRecord
     */
    public function setCurrentJobStartedAt(?int $startedAt): WorkerRecord
    {
        $this->currentJobStartedAt = $startedAt;
        return $this;
    }

    public function clearCurrentJob(): WorkerRecord
    {
        $this->currentJobId        = null;
        $this->currentQueue        = null;
        $this->currentJobStartedAt = null;
        $this->currentJobTimeout   = null;
        return $this;
    }

    public function getCurrentJobId(): ?string
    {
        return $this->currentJobId;
    }

    public function getCurrentQueue(): ?string
    {
        return $this->currentQueue;
    }

    public function getCurrentJobStartedAt(): ?int
    {
        return $this->currentJobStartedAt;
    }

    public function getCurrentJobTimeout(): ?int
    {
        return $this->currentJobTimeout;
    }

    public function incrementProcessed(): WorkerRecord
    {
        $this->jobsProcessed++;
        return $this;
    }

    public function incrementFailed(): WorkerRecord
    {
        $this->jobsFailed++;
        return $this;
    }

    public function getJobsProcessed(): int
    {
        return $this->jobsProcessed;
    }

    public function getJobsFailed(): int
    {
        return $this->jobsFailed;
    }

    /**
     * Whether this worker's heartbeat is older than the given threshold
     *
     * @param  int $seconds
     * @return bool
     */
    public function isStale(int $seconds = 90): bool
    {
        return ((time() - $this->lastSeenAt) > $seconds);
    }

    /**
     * How long the current job has been running, or null when idle
     *
     * @return ?int
     */
    public function getCurrentJobDuration(): ?int
    {
        return ($this->currentJobStartedAt !== null) ? (time() - $this->currentJobStartedAt) : null;
    }

    /**
     * Whether this worker looks stuck: its heartbeat is stale AND it has a
     * job in flight that has outlived its own timeout (or, when the job set
     * no timeout, the staleness threshold itself as the fallback yardstick).
     *
     * A stale worker with NO current job is deliberately not "stuck" - it is
     * wedged idle, which getStaleWorkers() surfaces instead.
     *
     * @param  int $staleSeconds
     * @return bool
     */
    public function isLikelyStuck(int $staleSeconds = 90): bool
    {
        if (!$this->isStale($staleSeconds) || ($this->currentJobId === null)) {
            return false;
        }

        $duration = $this->getCurrentJobDuration();
        if ($duration === null) {
            return false;
        }

        return ($this->currentJobTimeout !== null)
            ? ($duration > $this->currentJobTimeout)
            : ($duration > $staleSeconds);
    }

    /**
     * Flatten to a plain array for storage
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'host'                => $this->host,
            'pid'                 => $this->pid,
            'startedAt'           => $this->startedAt,
            'lastSeenAt'          => $this->lastSeenAt,
            'queues'              => $this->queues,
            'mode'                => $this->mode,
            'currentJobId'        => $this->currentJobId,
            'currentQueue'        => $this->currentQueue,
            'currentJobStartedAt' => $this->currentJobStartedAt,
            'currentJobTimeout'   => $this->currentJobTimeout,
            'jobsProcessed'       => $this->jobsProcessed,
            'jobsFailed'          => $this->jobsFailed,
        ];
    }

    /**
     * Rebuild from a stored array
     *
     * @param  array $data
     * @return WorkerRecord
     */
    public static function fromArray(array $data): WorkerRecord
    {
        $record = new self(
            (string)($data['id'] ?? ''),
            (string)($data['host'] ?? ''),
            (int)($data['pid'] ?? 0),
            (int)($data['startedAt'] ?? 0),
            (int)($data['lastSeenAt'] ?? 0)
        );

        $record->setName($data['name'] ?? null);
        $record->setQueues($data['queues'] ?? []);
        $record->setMode((string)($data['mode'] ?? self::MODE_SINGLE_PASS));
        $record->setCurrentJobStartedAt(
            isset($data['currentJobStartedAt']) ? (int)$data['currentJobStartedAt'] : null
        );

        if (!empty($data['currentJobId'])) {
            $record->currentJobId      = (string)$data['currentJobId'];
            $record->currentQueue      = $data['currentQueue'] ?? null;
            $record->currentJobTimeout = isset($data['currentJobTimeout'])
                ? (int)$data['currentJobTimeout'] : null;
        }

        $record->jobsProcessed = (int)($data['jobsProcessed'] ?? 0);
        $record->jobsFailed    = (int)($data['jobsFailed'] ?? 0);

        return $record;
    }

}
