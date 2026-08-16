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

use Pop\Db\Adapter\AbstractAdapter as DbAdapter;
use Pop\Db\Gateway\Table as DbTable;
use Pop\Db\Sql\AbstractSql as DbSql;
use Pop\Db\Sql\Where;
use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\PayloadSigner;
use Pop\Queue\Process\Task;

/**
 * Database adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Database extends AbstractTaskAdapter
{
    /**
     * How many candidate rows reserve() pulls back per scan.
     *
     * reserve() returns a single job, but it has to look past any candidate it
     * can't claim (delayed, backed off, corrupt, or won by another worker), so
     * it needs more than one row in hand - and, when a whole batch turns out to
     * be unclaimable, the freedom to page further back. Without a bound it read
     * the entire eligible set into PHP to hand back one job, which made the cost
     * of reserving grow with the depth of the queue: every worker paid for every
     * queued job, on every job it ran.
     *
     * Sized to clear the ordinary case in one round trip - a run of delayed jobs
     * at the head of the queue is the usual reason for a skip, and it is rarely
     * dozens long - while staying small enough that the payloads fetched
     * alongside them stay cheap.
     */
    protected const RESERVE_BATCH_SIZE = 50;

    /**
     * Database adapter
     * @var ?DbAdapter
     */
    protected ?DbAdapter $db = null;

    /**
     * Database table
     * @var ?string
     */
    protected ?string $table = null;

    /**
     * Reservation lease length, in seconds
     * @var int
     */
    protected int $leaseSeconds = 60;

    /**
     * Constructor
     *
     * Instantiate the database adapter object
     *
     * @param DbAdapter $db
     * @param string    $table
     * @param ?string   $priority
     * @param int       $leaseSeconds
     */
    public function __construct(DbAdapter $db, string $table = 'pop_queue', ?string $priority = null, int $leaseSeconds = 60)
    {
        $this->db           = $db;
        $this->table        = $table;
        $this->leaseSeconds = $leaseSeconds;

        if (!$this->db->hasTable($table)) {
            $this->createTable($table);
        } else {
            $this->ensureReservedUntilColumn($table);
            $this->ensureReservedByColumn($table);
        }

        parent::__construct($priority);
    }

    /**
     * Create database adapter
     *
     * @param  DbAdapter $db
     * @param  string    $table
     * @param  ?string   $priority
     * @param  int       $leaseSeconds
     * @return Database
     */
    public static function create(DbAdapter $db, string $table = 'pop_queue', ?string $priority = null, int $leaseSeconds = 60): Database
    {
        return new self($db, $table, $priority, $leaseSeconds);
    }

    /**
     * Get database adapter
     *
     * @return ?DbAdapter
     */
    public function getDb(): ?DbAdapter
    {
        return $this->db;
    }

    /**
     * Get database adapter (alias)
     *
     * @return ?DbAdapter
     */
    public function db(): ?DbAdapter
    {
        return $this->db;
    }

    /**
     * Get database table
     *
     * @return ?string
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Add the reserved_until column to a table created by an earlier version
     * of this adapter, if it isn't there already. Gated on a real
     * column-existence check (Pop\Db\Gateway\Table::getTableInfo(), backed
     * by PRAGMA table_info on SQLite / information_schema.columns on
     * Postgres+SQL Server / SHOW COLUMNS elsewhere - genuinely portable
     * across every backend this adapter supports), NOT on catching the
     * exception a duplicate-column ALTER is expected to throw: this repo's
     * SQLite adapter's query() only calls throwError() when the driver
     * reports a non-zero error code, and a duplicate-column ALTER TABLE ...
     * ADD COLUMN against SQLite returns false with error code 0 - it fails
     * silently (surfacing only as a PHP warning), so a try/catch around it
     * never fires. That previously let the backfill below re-run on every
     * single construction against an already-migrated table, zeroing out
     * reserved_until (and the lease it represents) for every currently
     * in-flight job, table-wide, on ordinary worker startup - exactly the
     * double-execution failure this task exists to prevent. Gating on the
     * real column check means the ALTER and backfill run exactly once, ever,
     * per table, and a genuine migration failure (permissions, locked table)
     * now throws normally instead of failing silently.
     *
     * The backfill sets reserved_until = 0 for any row already sitting at
     * status = 0 - a job reserved under the pre-lease Phase 1 contract.
     * Without it, such a row would have reserved_until = NULL forever, and
     * "NULL <= now" evaluates to NULL in SQL - matching neither the
     * "status = 1" nor the "status = 0 AND reserved_until <= now" branch of
     * reserve()'s eligibility check, making the row invisible to reserve()
     * permanently. Backfilling it to 0 makes it immediately eligible for
     * reclaim on the very next reserve() call, which is the correct
     * behavior for a job whose reservation state predates leasing entirely.
     *
     * @param  string $table
     * @return void
     */
    protected function ensureReservedUntilColumn(string $table): void
    {
        $info = (new DbTable($table))->getTableInfo($this->db);
        if (isset($info['columns']['reserved_until'])) {
            return;
        }

        $schema = $this->db->createSchema();
        $schema->alter($table)->addColumn('reserved_until', 'int', 16)->nullable();
        $this->db->query($schema);

        $backfill = $this->db->createSql();
        $backfill->update($table)->values(['reserved_until' => 0])
            ->where("type = 'job'")->andWhere('status = 0');
        $this->db->query($backfill);
    }

    /**
     * Add the reserved_by column to a table created by an earlier version of
     * this adapter, if it isn't there already. Same real column-existence
     * check as ensureReservedUntilColumn() (see that method's docblock for
     * why exception-based detection is unsafe here). reserved_by holds the
     * random claim token reserve() writes and re-reads to prove its own
     * claiming UPDATE actually won a given row - see reserve()'s docblock
     * for why that replaced an affected-row count.
     *
     * @param  string $table
     * @return void
     */
    protected function ensureReservedByColumn(string $table): void
    {
        $info = (new DbTable($table))->getTableInfo($this->db);
        if (isset($info['columns']['reserved_by'])) {
            return;
        }

        $schema = $this->db->createSchema();
        $schema->alter($table)->addColumn('reserved_by', 'varchar', 64)->nullable();
        $this->db->query($schema);
    }

    /**
     * Read back the reserved_by token currently stored for a row. Used by
     * reserve() to prove, via the real resulting row state rather than
     * driver-reported execute metadata, whether its own claiming UPDATE was
     * the one that actually won the row.
     *
     * @param  int $id
     * @return ?string
     */
    protected function claimedBy(int $id): ?string
    {
        $sql = $this->db->createSql();
        $sql->select('reserved_by')->from($this->table)->where('id = ' . $id);
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return $rows[0]['reserved_by'] ?? null;
    }

    /**
     * Build the eligibility predicate for claiming a task's current
     * due-window: eligible if no claim exists yet, the existing claim is
     * for a *different* window (a new tick is always claimable
     * immediately, regardless of the old claim's expiry), or the existing
     * claim is for the *same* window but has expired. The window is
     * encoded as a prefix of reserved_by itself ("<window>:<token>"), so
     * this needs no new column - reserved_until still means "this claim
     * expires at", reserved_by's prefix now also answers "for which
     * window". "reserved_by NOT LIKE '<window>:%'" is plain, portable SQL
     * LIKE - no dialect-specific string functions - and the window value
     * is purely numeric so it can never contain a LIKE wildcard.
     *
     * @param  DbSql  $sql
     * @param  string $taskId
     * @param  string $window
     * @param  int    $now
     * @return Where
     */
    protected function buildTaskClaimEligibleWhere(DbSql $sql, string $taskId, string $window, int $now): Where
    {
        $where = new Where($sql);
        $where->equalTo('type', 'task');
        $where->equalTo('job_id', $taskId);

        $group = $where->andNest();
        $group->isNull('reserved_by');
        $group->or();
        $group->notLike('reserved_by', $window . ':%');
        $group->or();
        $group->lessThanOrEqualTo('reserved_until', $now);

        return $where;
    }

    /**
     * Read back the reserved_by token currently stored for a task row.
     * Used by claimTaskRun() to prove, via the real resulting row state,
     * whether its own claiming UPDATE actually won - the same approach
     * claimedBy() uses for job claiming (see reserve()'s docblock for why
     * an affected-row count isn't portable enough for this).
     *
     * @param  string $taskId
     * @return ?string
     */
    protected function claimedByTaskId(string $taskId): ?string
    {
        $sql = $this->db->createSql();
        $sql->select('reserved_by')->from($this->table)->where("type = 'task'")->andWhere('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $taskId]);
        $this->db->execute();
        $rows = $this->db->fetchAll();

        return $rows[0]['reserved_by'] ?? null;
    }

    /**
     * Atomically claim a task's current due-window. See
     * buildTaskClaimEligibleWhere() for the eligibility rule.
     *
     * @param  string $taskId
     * @param  string $window
     * @return bool
     */
    public function claimTaskRun(string $taskId, string $window): bool
    {
        $now   = time();
        $token = $window . ':' . bin2hex(random_bytes(8));

        $sql    = $this->db->createSql();
        $update = $sql->update($this->table)->values([
            'reserved_until' => ':reserved_until',
            'reserved_by'    => ':reserved_by'
        ]);
        $update->where($this->buildTaskClaimEligibleWhere($update, $taskId, $window, $now));

        $this->db->prepare($sql);
        $this->db->bindParams([
            'reserved_until' => ($now + self::TASK_CLAIM_TTL),
            'reserved_by'    => $token
        ]);
        $this->db->execute();

        return ($this->claimedByTaskId($taskId) === $token);
    }

    /**
     * Get queue end index
     *
     * @return int
     */
    protected function getEndIndex(): int
    {
        $sql    = $this->db->createSql();
        $select = $sql->select('index')->from($this->table);

        // Kept off the where() chain on purpose - where() is declared to return
        // AbstractPredicateClause, which has no orderBy()/limit(), so chaining
        // past it loses the Select type even though the object really is one.
        $select->where('index IS NOT NULL');
        $select->orderBy('index', 'DESC')->limit(1);

        $this->db->query($sql);

        $rows = $this->db->fetchAll();
        return (isset($rows[0]['index'])) ? (int)$rows[0]['index'] : 0;
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @return Database
     */
    public function push(AbstractJob $job): Database
    {
        $sql = $this->db->createSql();
        $sql->insert($this->table)->values([
            'index'   => ':index',
            'type'    => ':type',
            'job_id'  => ':job_id',
            'payload' => ':payload',
            'status'  => ':status'
        ]);

        $this->db->prepare($sql);
        $this->db->bindParams([
            'index'   => ($this->getEndIndex() + 1),
            'type'    => 'job',
            'job_id'  => $job->getJobId(),
            'payload' => base64_encode(PayloadSigner::sign(serialize(clone $job))),
            'status'  => 1
        ]);
        $this->db->execute();

        return $this;
    }

    /**
     * Build the "pending, or claimed with an expired lease" predicate shared
     * by reserve()'s scan and its per-row claiming UPDATE: type = 'job' AND
     * (status = 1 OR (status = 0 AND reserved_until <= $now)). Built via
     * pop-db's nested PredicateSet API (Where::andNest()/orNest()) rather
     * than a raw SQL string, because pop-db's where()/andWhere() string
     * parser only understands simple "column operator value" expressions -
     * handing it a compound, parenthesized boolean expression silently
     * misparses it instead of raising an error.
     *
     * @param  DbSql $sql
     * @param  int   $now
     * @return Where
     */
    protected function buildEligibleWhere(DbSql $sql, int $now): Where
    {
        $where = new Where($sql);
        $where->equalTo('type', 'job');

        $leaseGroup = $where->andNest();
        $leaseGroup->equalTo('status', 1);

        $expiredGroup = $leaseGroup->orNest();
        $expiredGroup->equalTo('status', 0);
        $expiredGroup->and();
        $expiredGroup->lessThanOrEqualTo('reserved_until', $now);

        return $where;
    }

    /**
     * Atomically claim the next eligible job. Scans pending/expired-lease
     * rows in queue order, skipping any job that isn't yet available (delayed
     * or backed off), and atomically claims the first eligible one via a
     * conditional UPDATE that writes a random claim token into reserved_by
     * alongside status/reserved_until - all in the same statement.
     *
     * The scan runs in batches of RESERVE_BATCH_SIZE rather than over the whole
     * eligible set at once, paging forward only when an entire batch turns out
     * to be unclaimable. Paging is inherently approximate - rows claimed by
     * other workers between batches shift the offset, so a candidate can slip
     * past - but the scan was already racy by construction (that is what the
     * read-back below exists to handle), and anything missed is simply picked up
     * by the next reserve() call rather than lost.
     *
     * Success is proven by re-reading reserved_by immediately after the
     * UPDATE and comparing it to the token this call generated, not by
     * inspecting the UPDATE's driver-reported affected-row count.
     * getNumberOfAffectedRows() is not portable enough for this: pop-db's
     * Pgsql adapter reads it off the *prepare* result rather than the
     * *execute* result, so pg_affected_rows() on a PREPARE always reports 0
     * - meaning a real, successful claim on Postgres would still read back
     * as "0 affected rows" and reserve() would treat every genuine win as a
     * lost race, lease every row it touches, and never return a job. Reading
     * back the actual resulting row state instead works identically across
     * every backend, because it isn't asking the driver to describe what it
     * did - it's asking the database what's really there now.
     *
     * If reserved_by doesn't come back as this call's token (someone else's
     * token is there, or the row's state changed/vanished), this worker lost
     * the race between the scan and its UPDATE, so this moves on to the next
     * candidate instead of assuming success.
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $now    = time();
        $offset = 0;

        while (true) {
            $sql    = $this->db->createSql();
            $select = $sql->select(['id', 'index', 'payload'])->from($this->table);
            $select->where($this->buildEligibleWhere($select, $now));
            $select->orderBy('index', ($this->isFifo()) ? 'ASC' : 'DESC');
            $select->limit(self::RESERVE_BATCH_SIZE);
            if ($offset > 0) {
                $select->offset($offset);
            }

            $this->db->query($sql);
            $rows = $this->db->fetchAll();

            if (empty($rows)) {
                return null;
            }

            foreach ($rows as $row) {
                // Suppressed: a corrupt/truncated payload makes unserialize() emit a
                // warning and return false, and a payload whose class no longer
                // exists (renamed/removed in a deploy) yields a
                // __PHP_Incomplete_Class - the instanceof check below handles both.
                // A payload that fails PayloadSigner::verify() (tampered, or written
                // by something other than this application when a signing key is
                // configured) is treated identically - $raw is false, so
                // unserialize() is never called on it at all.
                $raw = PayloadSigner::verify(base64_decode($row['payload']));
                $job = ($raw !== false) ? @unserialize($raw) : false;

                if (!($job instanceof AbstractJob)) {
                    // Corrupt/unloadable payload - skip rather than claim it and
                    // then blow up returning a non-AbstractJob. Claiming it would
                    // be worse than a one-shot crash now that leases exist: the
                    // claim would expire, get reclaimed back to eligible, and
                    // poison the next worker too, forever.
                    continue;
                }

                if (!$job->isAvailable()) {
                    continue;
                }

                $id    = (int)$row['id'];
                $token = bin2hex(random_bytes(16));

                $sql    = $this->db->createSql();
                $update = $sql->update($this->table)->values([
                    'status'         => ':status',
                    'reserved_until' => ':reserved_until',
                    'reserved_by'    => ':reserved_by'
                ]);
                $update->where($this->buildEligibleWhere($update, $now));
                $update->andWhere('id = ' . $id);

                $this->db->prepare($sql);
                $this->db->bindParams([
                    'status'         => 0,
                    'reserved_until' => ($now + $this->leaseSeconds),
                    'reserved_by'    => $token
                ]);
                $this->db->execute();

                if ($this->claimedBy($id) === $token) {
                    return $job;
                }
                // Lost the race to another worker between the scan and this UPDATE - try the next candidate.
            }

            // Nothing in this batch was claimable - every row was corrupt, not
            // yet available, or lost to another worker. A short batch means
            // there is nothing further back to look at; a full one means there
            // might be, so page forward rather than give up. A queue whose head
            // is a run of delayed jobs still has runnable ones behind them.
            if (count($rows) < self::RESERVE_BATCH_SIZE) {
                return null;
            }

            $offset += self::RESERVE_BATCH_SIZE;
        }
    }

    /**
     * Put a job back to pending, honoring its backoff schedule unless an
     * explicit delay is given
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return Database
     */
    public function release(AbstractJob $job, ?int $delay = null): Database
    {
        $job->delay($delay ?? $job->getBackoffDelay());

        $sql = $this->db->createSql();
        $sql->update($this->table)->values([
            'payload'        => ':payload',
            'status'         => ':status',
            'reserved_until' => ':reserved_until',
            'reserved_by'    => ':reserved_by'
        ])->where("type = 'job'")->andWhere('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams([
            'payload'        => base64_encode(PayloadSigner::sign(serialize(clone $job))),
            'status'         => 1,
            'reserved_until' => null,
            'reserved_by'    => null,
            'job_id'         => $job->getJobId()
        ]);
        $this->db->execute();

        return $this;
    }

    /**
     * Permanently remove a job
     *
     * @param  AbstractJob $job
     * @return Database
     */
    public function delete(AbstractJob $job): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where("type = 'job'")->andWhere('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $job->getJobId()]);
        $this->db->execute();

        return $this;
    }

    /**
     * Move a job to the dead-letter store
     *
     * @param  AbstractJob $job
     * @param  ?string     $reason
     * @return Database
     */
    public function bury(AbstractJob $job, ?string $reason = null): Database
    {
        $this->delete($job);

        $sql = $this->db->createSql();
        $sql->insert($this->table)->values([
            'type'    => ':type',
            'job_id'  => ':job_id',
            'payload' => ':payload'
        ]);

        $this->db->prepare($sql);
        $this->db->bindParams([
            'type'    => 'dead',
            'job_id'  => $job->getJobId(),
            'payload' => base64_encode(PayloadSigner::sign(serialize(clone $job)))
        ]);
        $this->db->execute();

        return $this;
    }

    /**
     * Check if adapter has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return ($this->count() > 0);
    }

    /**
     * Count of pending + reserved jobs
     *
     * @return int
     */
    public function count(): int
    {
        $sql = $this->db->createSql();
        $sql->select(['total' => 'COUNT(1)'])->from($this->table)->where("type = 'job'");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return (isset($rows[0]['total'])) ? (int)$rows[0]['total'] : 0;
    }

    /**
     * Clear pending and reserved jobs (not tasks or dead-letter jobs)
     *
     * @return Database
     */
    public function clear(): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where("type = 'job'");
        $this->db->query($sql);

        return $this;
    }

    /**
     * Check if adapter has dead-letter jobs
     *
     * @return bool
     */
    public function hasDeadJobs(): bool
    {
        return ($this->countDead() > 0);
    }

    /**
     * Count of dead-letter jobs
     *
     * @return int
     */
    public function countDead(): int
    {
        $sql = $this->db->createSql();
        $sql->select(['total' => 'COUNT(1)'])->from($this->table)->where("type = 'dead'");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return (isset($rows[0]['total'])) ? (int)$rows[0]['total'] : 0;
    }

    /**
     * Get dead-letter jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getDeadJobs(bool $unserialize = true): array
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where("type = 'dead'");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();
        $jobs = [];

        foreach ($rows as $row) {
            if (!$unserialize) {
                $jobs[$row['job_id']] = $row;
                continue;
            }
            $raw = PayloadSigner::verify(base64_decode($row['payload']));
            $jobs[$row['job_id']] = ($raw !== false) ? unserialize($raw) : false;
        }

        return $jobs;
    }

    /**
     * Get a dead-letter job
     *
     * @param  string $jobId
     * @param  bool   $unserialize
     * @return mixed
     */
    public function getDeadJob(string $jobId, bool $unserialize = true): mixed
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where("type = 'dead'")->andWhere('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();
        $rows = $this->db->fetchAll();

        if (!isset($rows[0]['payload'])) {
            return null;
        }

        if (!$unserialize) {
            return $rows[0];
        }

        $raw = PayloadSigner::verify(base64_decode($rows[0]['payload']));
        return ($raw !== false) ? unserialize($raw) : false;
    }

    /**
     * Move a dead-letter job back to pending
     *
     * @param  string $jobId
     * @return Database
     */
    public function retryDeadJob(string $jobId): Database
    {
        $job = $this->getDeadJob($jobId);
        if ($job instanceof AbstractJob) {
            $this->deleteDeadJob($jobId);
            $this->push($job);
        }

        return $this;
    }

    /**
     * Permanently remove a dead-letter job
     *
     * @param  string $jobId
     * @return Database
     */
    public function deleteDeadJob(string $jobId): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where("type = 'dead'")->andWhere('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        return $this;
    }

    /**
     * Clear all dead-letter jobs
     *
     * @return Database
     */
    public function clearDead(): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where("type = 'dead'");
        $this->db->query($sql);

        return $this;
    }

    /**
     * Schedule job with queue
     *
     * @param  Task $task
     * @return Database
     */
    public function schedule(Task $task): Database
    {
        if ($task->isValid()) {
            $sql = $this->db->createSql();
            $sql->insert($this->table)->values([
                'type'    => ':type',
                'job_id'  => ':job_id',
                'payload' => ':payload'
            ]);

            $jobData = [
                'type'    => 'task',
                'job_id'  => $task->getJobId(),
                'payload' => base64_encode(PayloadSigner::sign(serialize(clone $task)))
            ];

            $this->db->prepare($sql);
            $this->db->bindParams($jobData);
            $this->db->execute();
        }

        return $this;
    }

    /**
     * Get scheduled tasks
     *
     * @return array
     */
    public function getTasks(): array
    {
        $sql = $this->db->createSql();
        $sql->select('job_id')->from($this->table)->where("type = 'task'");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        $tasks = [];

        foreach ($rows as $row) {
            $tasks[] = $row['job_id'];
        }

        return $tasks;
    }

    /**
     * Get scheduled task
     *
     * @param  string $taskId
     * @return ?Task
     */
    public function getTask(string $taskId): ?Task
    {
        $sql = $this->db->createSql();
        $sql->select('payload')->from($this->table)->where("type = 'task'")->andWhere('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $taskId]);
        $this->db->execute();
        $rows = $this->db->fetchAll();

        if (!isset($rows[0]['payload'])) {
            return null;
        }

        // Guarded with instanceof rather than returning unserialize()'s result
        // directly, the same way reserve() does: a corrupt or tampered payload
        // makes unserialize() return false, and false out of a ": ?Task" method
        // is a TypeError, not a null.
        $raw  = PayloadSigner::verify(base64_decode($rows[0]['payload']));
        $task = ($raw !== false) ? @unserialize($raw) : false;

        return ($task instanceof Task) ? $task : null;
    }

    /**
     * Get every scheduled task, keyed by task ID.
     *
     * One query for the whole set, instead of the inherited "list the IDs, then
     * SELECT each payload by ID" - which is a query per scheduled task, run on
     * every Queue::run() and so on every tick of a worker's schedule loop. The
     * payloads are decoded exactly as getTask() decodes its one, corrupt entries
     * omitted rather than returned.
     *
     * @return array  taskId => Task
     */
    public function getAllTasks(): array
    {
        $sql = $this->db->createSql();
        $sql->select(['job_id', 'payload'])->from($this->table)->where("type = 'task'");
        $this->db->query($sql);

        $tasks = [];

        foreach ($this->db->fetchAll() as $row) {
            $raw  = PayloadSigner::verify(base64_decode($row['payload']));
            $task = ($raw !== false) ? @unserialize($raw) : false;

            if ($task instanceof Task) {
                $tasks[$row['job_id']] = $task;
            }
        }

        return $tasks;
    }

    /**
     * Update scheduled task
     *
     * @param  Task $task
     * @return Database
     */
    public function updateTask(Task $task): Database
    {
        if ($task->isValid()) {
            $sql = $this->db->createSql();
            $sql->update($this->table)->values([
                'payload' => ':payload'
            ])->where("type = 'task'")->andWhere('job_id = :job_id');

            $jobData = [
                'payload' => base64_encode(PayloadSigner::sign(serialize(clone $task))),
                'job_id'  => $task->getJobId()
            ];

            $this->db->prepare($sql);
            $this->db->bindParams($jobData);
            $this->db->execute();
        } else {
            $this->removeTask($task->getJobId());
        }

        return $this;
    }

    /**
     * Remove scheduled task
     *
     * @param  string $taskId
     * @return Database
     */
    public function removeTask(string $taskId): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where("type = 'task'")->andWhere('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $taskId]);
        $this->db->execute();

        return $this;
    }

    /**
     * Get scheduled tasks count
     *
     * @return int
     */
    public function getTaskCount(): int
    {
        $sql = $this->db->createSql();
        $sql->select(['total' => 'COUNT(1)'])->from($this->table)->where("type = 'task'");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return (isset($rows[0]['total'])) ? (int)$rows[0]['total'] : 0;
    }

    /**
     * Has scheduled tasks
     *
     * @return bool
     */
    public function hasTasks(): bool
    {
        return ($this->getTaskCount() > 0);
    }

    /**
     * Clear all scheduled task
     *
     * @return Database
     */
    public function clearTasks(): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where("type = 'task'");
        $this->db->query($sql);

        return $this;
    }

    /**
     * Create the database table
     *
     * @param  string $table
     * @return Database
     */
    public function createTable(string $table): Database
    {
        $schema = $this->db->createSchema();

        $schema->create($table)
            ->int('id', 16)->increment()
            ->int('index', 16)->nullable()
            ->varchar('type', 255)
            ->varchar('job_id', 255)
            ->text('payload')
            ->int('status', 1)->defaultIs(1)
            ->int('reserved_until', 16)->nullable()
            ->varchar('reserved_by', 64)->nullable()
            ->primary('id');

        $this->db->query($schema);

        $this->createIndexes($table);

        return $this;
    }

    /**
     * Add the indexes the hot queries need to a table this adapter just created.
     *
     * Every query on the hot paths filters on type and then either orders by
     * index (reserve(), getEndIndex()) or looks a row up by job_id (getTask(),
     * claimedByTaskId(), the dead-letter accessors). Unindexed, all of those are
     * full table scans, and reserve()'s is a scan plus a sort - paid by every
     * worker on every job it runs, against a table whose whole purpose is to
     * accumulate rows.
     *
     * Issued as separate statements, and separate schema objects, for two
     * reasons that are easy to get wrong:
     *
     *  - Chaining index() onto the create() above renders valid DDL but does not
     *    execute it. The rendered schema becomes several statements separated by
     *    semicolons, and the adapters hand the whole string to one driver call -
     *    SQLite3::query() runs the first statement and silently discards the
     *    rest, so the table would appear and the indexes just wouldn't.
     *  - Casting a schema object to string consumes it. Rendering one to inspect
     *    it (or reusing one across two query() calls) leaves an empty builder
     *    behind, which also fails silently.
     *
     * The index names are given explicitly because deriving them would involve
     * the "index" column, a reserved word on every backend here.
     *
     * Only ever called for a table this adapter is creating from scratch. An
     * existing table is left alone deliberately: building an index on a live
     * queue table can hold a lock for as long as the table is large, which is a
     * decision for whoever operates the database, not one a library should make
     * on their behalf on first connect.
     *
     * @param  string $table
     * @return void
     */
    protected function createIndexes(string $table): void
    {
        foreach ([
            $table . '_type_index_idx'  => ['type', 'index'],
            $table . '_type_job_id_idx' => ['type', 'job_id'],
        ] as $name => $columns) {
            $schema = $this->db->createSchema();
            $schema->alter($table)->index($columns, $name);
            $this->db->query($schema);
        }
    }

}
