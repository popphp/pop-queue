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

use Pop\Db\Adapter\AbstractAdapter as DbAdapter;
use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\Task;

/**
 * Database adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
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
     * Get queue length
     *
     * @return int
     */
    public function getStart(): int
    {
        $sql = $this->db->createSql();
        $sql->select('index')->from($this->table)->where('index IS NOT NULL')->orderBy('index')->limit(1);
        $this->db->query($sql);

        $rows = $this->db->fetchAll();
        return (isset($rows[0]['index'])) ? (int)$rows[0]['index'] : 0;
    }

    /**
     * Get queue length
     *
     * @return int
     */
    public function getEnd(): int
    {
        $sql = $this->db->createSql();
        $sql->select('index')->from($this->table)->orderBy('index', 'DESC')->limit(1);
        $this->db->query($sql);

        $rows = $this->db->fetchAll();
        return (isset($rows[0]['index'])) ? (int)$rows[0]['index'] : 0;
    }

    /**
     * Get queue job status
     *
     * @param  int $index
     * @return int
     */
    public function getStatus(int $index): int
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
        $status = 1;
        $index  = ($this->getEnd() + 1);

        if ($job->hasFailed()) {
            $status = 2;
            if ($this->isFilo()) {
                $index = ($this->getStart() - 1);
            }
        }

        if ($job->isValid()) {
            $sql = $this->db->createSql();
            $sql->insert($this->table)->values([
                'index'   => ':index',
                'type'    => ':type',
                'job_id'  => ':job_id',
                'payload' => ':payload',
                'status'  => ':status'
            ]);

            $jobData = [
                'index'   => $index,
                'type'    => 'job',
                'job_id'  => $job->getJobId(),
                'payload' => serialize(clone $job),
                'status'  => $status
            ];

            $this->db->prepare($sql);
            $this->db->bindParams($jobData);
            $this->db->execute();
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
        $index  = ($this->isFifo()) ? $this->getStart() : $this->getEnd();
        $status = $this->getStatus($index);

        if ($status != 0) {
            $sql = $this->db->createSql();
            $sql->update($this->table)->values(['status' => 0])->where('index = ' . (int)$index);
            $this->db->query($sql);

            $sql->select('payload')->from($this->table)->where('index = ' . (int)$index);
            $this->db->query($sql);
            $rows = $this->db->fetchAll();
            if (isset($rows[0]['payload'])) {
                $job = $rows[0]['payload'];
            }
            $sql->delete()->from($this->table)->where('index = ' . (int)$index);
            $this->db->query($sql);
        }

        return ($job !== false) ? unserialize($job) : null;
    }

    /**
     * Check if adapter has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        $sql = $this->db->createSql();
        $sql->select(['total' => 'COUNT(1)'])->from($this->table)->where("type = 'job'");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return (isset($rows[0]['total'])) ? (int)$rows[0]['total'] : 0;
    }

    /**
     * Check if adapter has failed job
     *
     * @param  int $index
     * @return bool
     */
    public function hasFailedJob(int $index): bool
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where('index = ' . (int)$index);
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return (isset($rows[0]));
    }

    /**
     * Get failed job from worker by job ID
     *
     * @param  int $index
     * @param  bool $unserialize
     * @return mixed
     */
    public function getFailedJob(int $index, bool $unserialize = true): mixed
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where('index = ' . (int)$index);
        $this->db->query($sql);
        $rows = $this->db->fetchAll();
        $job  = null;
        if (isset($rows[0])) {
            $job = ($unserialize) ? unserialize($rows[0]['payload']) : $rows[0];
        }

        return $job;
    }

    /**
     * Check if adapter has failed jobs
     *
     * @return bool
     */
    public function hasFailedJobs(): bool
    {
        $sql = $this->db->createSql();
        $sql->select(['total' => 'COUNT(1)'])->from($this->table)->where("status = 2");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();

        return (isset($rows[0]['total'])) ? (int)$rows[0]['total'] : 0;
    }

    /**
     * Get adapter failed jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getFailedJobs(bool $unserialize = true): array
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where("status = 2");
        $this->db->query($sql);
        $rows = $this->db->fetchAll();
        $jobs = [];

        foreach ($rows as $row) {
            $jobs[$row['index']] = ($unserialize) ? unserialize($row['payload']) : $row;
        }

        return $jobs;
    }

    /**
     * Clear failed jobs out of the queue
     *
     * @return Database
     */
    public function clearFailed(): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where("status = 2");
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
                'payload' => serialize(clone $task)
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

        return (isset($rows[0]['payload'])) ? unserialize($rows[0]['payload']) : null;
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
                'payload' => serialize(clone $task),
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
     * Clear jobs out of queue
     *
     * @return Database
     */
    public function clear(): Database
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table);
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