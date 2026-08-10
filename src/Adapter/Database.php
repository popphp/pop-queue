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

use Pop\Db\Adapter\AbstractAdapter as DbAdapter;
use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * Database adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2026 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.3
 */
class Database extends AbstractTaskAdapter
{
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
     * Constructor
     *
     * Instantiate the database adapter object
     *
     * @param DbAdapter $db
     * @param string    $table
     */
    public function __construct(DbAdapter $db, string $table = 'pop_queue', ?string $priority = null)
    {
        $this->db    = $db;
        $this->table = $table;

        if (!$this->db->hasTable($table)) {
            $this->createTable($table);
        }

        parent::__construct($priority);
    }

    /**
     * Create database adapter
     *
     * @param  DbAdapter $db
     * @param  string    $table
     * @return Database
     */
    public static function create(DbAdapter $db, string $table = 'pop_queue', ?string $priority = null): Database
    {
        return new self($db, $table);
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
     * Get queue start index
     *
     * @return int
     */
    protected function getStartIndex(): int
    {
        $sql = $this->db->createSql();
        $sql->select('index')->from($this->table)->where('index IS NOT NULL')->orderBy('index')->limit(1);
        $this->db->query($sql);

        $rows = $this->db->fetchAll();
        return (isset($rows[0]['index'])) ? (int)$rows[0]['index'] : 0;
    }

    /**
     * Get queue end index
     *
     * @return int
     */
    protected function getEndIndex(): int
    {
        $sql = $this->db->createSql();
        $sql->select('index')->from($this->table)->where('index IS NOT NULL')->orderBy('index', 'DESC')->limit(1);
        $this->db->query($sql);

        $rows = $this->db->fetchAll();
        return (isset($rows[0]['index'])) ? (int)$rows[0]['index'] : 0;
    }

    /**
     * Get queue slot status
     *
     * @param  int $index
     * @return int
     */
    protected function getSlotStatus(int $index): int
    {
        $sql = $this->db->createSql();
        $sql->select('status')->from($this->table)->where('index = ' . (int)$index);
        $this->db->query($sql);

        $rows = $this->db->fetchAll();
        return (isset($rows[0]['status'])) ? (int)$rows[0]['status'] : 0;
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
            'payload' => base64_encode(serialize(clone $job)),
            'status'  => 1
        ]);
        $this->db->execute();

        return $this;
    }

    /**
     * Atomically claim the next eligible job
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $index  = ($this->isFifo()) ? $this->getStartIndex() : $this->getEndIndex();
        $status = $this->getSlotStatus($index);

        if ($status != 1) {
            return null;
        }

        $sql = $this->db->createSql();
        $sql->update($this->table)->values(['status' => 0])->where('index = ' . (int)$index);
        $this->db->query($sql);

        $sql->select('payload')->from($this->table)->where('index = ' . (int)$index);
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return isset($rows[0]['payload']) ? unserialize(base64_decode($rows[0]['payload'])) : null;
    }

    /**
     * Put a job back to pending
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return Database
     */
    public function release(AbstractJob $job, ?int $delay = null): Database
    {
        $sql = $this->db->createSql();
        $sql->update($this->table)->values([
            'payload' => ':payload',
            'status'  => ':status'
        ])->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams([
            'payload' => base64_encode(serialize(clone $job)),
            'status'  => 1,
            'job_id'  => $job->getJobId()
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
        $sql->delete()->from($this->table)->where('job_id = :job_id');
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
            'payload' => base64_encode(serialize(clone $job))
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
            $jobs[$row['job_id']] = $unserialize ? unserialize(base64_decode($row['payload'])) : $row;
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
        $sql->select()->from($this->table)->where('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();
        $rows = $this->db->fetchAll();

        if (!isset($rows[0]['payload'])) {
            return null;
        }

        return $unserialize ? unserialize(base64_decode($rows[0]['payload'])) : $rows[0];
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
        $sql->delete()->from($this->table)->where('job_id = :job_id');
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
                'payload' => base64_encode(serialize(clone $task))
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
        $sql->select('payload')->from($this->table)->where('job_id = :job_id');
        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $taskId]);
        $this->db->execute();
        $rows = $this->db->fetchAll();

        return (isset($rows[0]['payload'])) ? unserialize(base64_decode($rows[0]['payload'])) : null;
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
            ])->where('job_id = :job_id');

            $jobData = [
                'payload' => base64_encode(serialize(clone $task)),
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
        $sql->delete()->from($this->table)->where('job_id = :job_id');
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
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

}
