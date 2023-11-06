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
use Pop\Queue\Worker;
use Pop\Queue\Processor\AbstractJob;

/**
 * Database worker adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Database extends AbstractAdapter
{

    /**
     * Database adapter
     * @var ?DbAdapter
     */
    protected ?DbAdapter $db = null;

    /**
     * Job table
     * @var string
     */
    protected string $table = 'pop_worker_jobs';

    /**
     * Failed job table
     * @var ?string
     */
    protected string $failedTable = 'pop_worker_failed_jobs';

    /**
     * Constructor
     *
     * Instantiate the database worker object
     *
     * @param DbAdapter $db
     * @param string    $table
     * @param string    $failedTable
     */
    public function __construct(
        DbAdapter $db, string $table = 'pop_worker_jobs', string $failedTable = 'pop_worker_failed_jobs'
    )
    {
        $this->db          = $db;
        $this->table       = $table;
        $this->failedTable = $failedTable;

        if (!$this->db->hasTable($table)) {
            $this->createTable($table);
        }
        if (!$this->db->hasTable($failedTable)) {
            $this->createFailedTable($failedTable);
        }
    }

    /**
     * Create database adapter
     *
     * @param  DbAdapter $db
     * @param  string    $table
     * @param  string    $failedTable
     * @return Database
     */
    public static function create(
        DbAdapter $db, string $table = 'pop_worker_jobs', string $failedTable = 'pop_worker_failed_jobs'
    ): Database
    {
        return new self($db, $table, $failedTable);
    }

    /**
     * Get all workers currently registered with this adapter
     *
     * @return array
     */
    public function getWorkers(): array
    {
        $sql = $this->db->createSql();
        $sql->select('worker')->from($this->table);

        $this->db->query($sql);

        $rows = $this->db->fetchAll();
        return array_column($rows, 'worker');
    }

    /**
     * Check if worker stack has job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasJob(mixed $jobId): bool
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get job from worker stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getJob(mixed $jobId, bool$unserialize = true): array
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where('job_id = :job_id')->limit(1);

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        $rows = $this->db->fetchAll();
        $row  = [];

        if (isset($rows[0])) {
            $row = $rows[0];
            if (($unserialize) && isset($row['payload'])) {
                $row['payload'] = unserialize(base64_decode($row['payload']));
            }
        }

        return $row;
    }

    /**
     * Save job in worker
     *
     * @param  string $workerName
     * @param  mixed $job
     * @param  array $jobData
     * @return string
     */
    public function saveJob(string $workerName, mixed $job, array $jobData) : string
    {
        $sql = $this->db->createSql();
        $sql->insert($this->table)->values([
            'job_id'       => ':job_id',
            'worker'       => ':worker',
            'payload'      => ':payload',
            'priority'     => ':priority',
            'max_attempts' => ':max_attempts',
            'attempts'     => ':attempts',
            'completed'    => ':completed'
        ]);

        $this->db->prepare($sql);
        $this->db->bindParams($jobData);
        $this->db->execute();

        return $jobData['job_id'];
    }

    /**
     * Update job from worker stack by job ID
     *
     * @param  AbstractJob $job
     * @return void
     */
    public function updateJob(AbstractJob $job): void
    {
        $jobId     = $job->getJobId();
        $jobData   = $this->getJob($jobId);
        $completed = $job->getCompleted();
        $values    = [];
        $params    = [];

        $values['payload'] = ':payload';
        $params['payload'] = base64_encode(serialize(clone $job));

        if (!empty($completed)) {
            $values['completed'] = ':completed';
            $params['completed'] = date('Y-m-d H:i:s', $completed);
        }
        if (isset($jobData['attempts'])) {
            $values['attempts'] = ':attempts';
            $params['attempts'] = $job->getAttempts();
        }

        $params['job_id'] = $jobId;

        $sql = $this->db->createSql();
        $sql->update($this->table)->values($values)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams($params);
        $this->db->execute();
    }

    /**
     * Check if worker has jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasJobs(mixed $worker): bool
    {
        $workerName  = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $placeholder = $this->db->createSql()->getPlaceholder();

        if ($placeholder == ':') {
            $placeholder .= 'worker';
        } else if ($placeholder == '$') {
            $placeholder .= '1';
        }

        $sqlString = <<<SQL
SELECT *
FROM `pop_worker_jobs`
WHERE
  `worker` = {$placeholder} AND
  ((`completed` IS NULL) OR ((`completed` IS NOT NULL) AND ((`max_attempts` = 0) OR (`attempts` < `max_attempts`))));
SQL;

        $this->db->prepare($sqlString);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get worker jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getJobs(mixed $worker, bool $unserialize = true): array
    {
        $workerName  = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $placeholder = $this->db->createSql()->getPlaceholder();

        if ($placeholder == ':') {
            $placeholder .= 'worker';
        } else if ($placeholder == '$') {
            $placeholder .= '1';
        }

        $sqlString = <<<SQL
SELECT *
FROM `pop_worker_jobs`
WHERE
  `worker` = {$placeholder} AND
  ((`completed` IS NULL) OR ((`completed` IS NOT NULL) AND ((`max_attempts` = 0) OR (`attempts` < `max_attempts`))));
SQL;

        $this->db->prepare($sqlString);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();

        $rows = $this->db->fetchAll();

        if ($unserialize) {
            foreach ($rows as $i => $row) {
                $rows[$i]['payload'] = unserialize(base64_decode($row['payload']));
            }
        }

        return $rows;
    }

    /**
     * Check if worker stack has completed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasCompletedJob(mixed $jobId): bool
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('job_id = :job_id')
            ->where('completed IS NOT NULL');

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Check if worker has completed jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasCompletedJobs(mixed $worker): bool
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('worker = :worker')
            ->where('completed IS NOT NULL');

        $this->db->prepare($sql);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get worker completed job
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJob(mixed $jobId, bool $unserialize = true): array
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('job_id = :job_id')
            ->where('completed IS NOT NULL')->limit(1);

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        $rows = $this->db->fetchAll();
        $row  = null;

        if (isset($rows[0])) {
            $row = $rows[0];
            if (($unserialize) && isset($row['payload'])) {
                $row['payload'] = unserialize(base64_decode($row['payload']));
            }
        }

        return $row;
    }

    /**
     * Get worker completed jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getCompletedJobs(mixed $worker, bool $unserialize = true): array
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('worker = :worker')
            ->where('completed IS NOT NULL');

        $this->db->prepare($sql);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();

        $rows = $this->db->fetchAll();

        if ($unserialize) {
            foreach ($rows as $i => $row) {
                $rows[$i]['payload'] = unserialize(base64_decode($row['payload']));
            }
        }

        return $rows;
    }

    /**
     * Check if worker stack has failed job
     *
     * @param  mixed $jobId
     * @return bool
     */
    public function hasFailedJob(mixed $jobId): bool
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get failed job from worker stack by job ID
     *
     * @param  mixed $jobId
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJob(mixed $jobId, bool $unserialize = true): array
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where('job_id = :job_id')->limit(1);

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        $rows = $this->db->fetchAll();
        $row  = null;

        if (isset($rows[0])) {
            $row = $rows[0];
            if ($unserialize) {
                $row['payload'] = unserialize(base64_decode($row['payload']));
            }
        }

        return $row;
    }

    /**
     * Check if worker adapter has failed jobs
     *
     * @param  mixed $worker
     * @return bool
     */
    public function hasFailedJobs(mixed $worker): bool
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where('worker = :worker');

        $this->db->prepare($sql);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get worker jobs
     *
     * @param  mixed $worker
     * @param  bool  $unserialize
     * @return array
     */
    public function getFailedJobs(mixed $worker, bool $unserialize = true): array
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where("worker = :worker");

        $this->db->prepare($sql);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();

        $rows = $this->db->fetchAll();

        if ($unserialize) {
            foreach ($rows as $i => $row) {
                $rows[$i]['payload'] = unserialize(base64_decode($row['payload']));
            }
        }

        return $rows;
    }

    /**
     * Push job onto worker stack
     *
     * @param  mixed $worker
     * @param  mixed $job
     * @param  mixed $priority
     * @return string
     */
    public function push(mixed $worker, mixed $job, mixed $priority = null): string
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;
        $jobId     = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();
        $jobData   = [
            'job_id'       => $jobId,
            'worker'       => $workerName,
            'payload'      => base64_encode(serialize(clone $job)),
            'priority'     => $priority,
            'max_attempts' => $job->getMaxAttempts(),
            'attempts'     => 0,
            'completed'    => null
        ];

        return $this->saveJob($workerName, $job, $jobData);
    }

    /**
     * Move failed job to failed worker stack
     *
     * @param  mixed           $worker
     * @param  mixed           $failedJob
     * @param  \Exception|null $exception
     * @return void
     */
    public function failed(mixed $worker, mixed $failedJob, \Exception|null $exception = null): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $sql = $this->db->createSql();
        $sql->insert($this->failedTable)->values([
            'job_id'    => ':job_id',
            'worker'    => ':worker',
            'payload'   => ':payload',
            'exception' => ':exception',
            'failed'    => ':failed'
        ]);

        $jobRecord = $this->getJob($failedJob, false);

        $this->db->prepare($sql);
        $this->db->bindParams([
            'job_id'    => $failedJob,
            'worker'    => $workerName,
            'payload'   => (isset($jobRecord['payload'])) ? $jobRecord['payload'] : null,
            'exception' => ($exception !== null) ? $exception->getMessage() : null,
            'failed'    => date('Y-m-d H:i:s')
        ]);

        $this->db->execute();

        if (!empty($jobId)) {
            $this->pop($jobId);
        }
    }

    /**
     * Pop job off of worker stack
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop(mixed $jobId): void
    {
        $sql = $this->db->createSql();
        $sql->delete($this->table)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();
    }

    /**
     * Clear jobs off of the worker stack
     *
     * @param  mixed $worker
     * @param  bool  $all
     * @return void
     */
    public function clear(mixed $worker, bool $all = false): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $sql = $this->db->createSql();
        $sql->delete($this->table)
            ->where('worker = :worker');

        if (!$all) {
            $sql->delete()->where('completed IS NOT NULL');
        }

        $this->db->prepare($sql);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();
    }

    /**
     * Clear failed jobs off of the worker stack
     *
     * @param  mixed $worker
     * @return void
     */
    public function clearFailed(mixed $worker): void
    {
        $workerName = ($worker instanceof Worker) ? $worker->getName() : $worker;

        $sql = $this->db->createSql();
        $sql->delete($this->failedTable)
            ->where('worker = :worker');

        $this->db->prepare($sql);
        $this->db->bindParams(['worker' => $workerName]);
        $this->db->execute();
    }

    /**
     * Flush all jobs off of the worker stack
     *
     * @param  bool $all
     * @return void
     */
    public function flush(bool $all = false): void
    {
        $sql = $this->db->createSql();
        $sql->delete($this->table);

        if (!$all) {
            $sql->delete()->where('completed IS NOT NULL');
        }

        $this->db->query($sql);
    }

    /**
     * Flush all failed jobs off of the worker stack
     *
     * @return void
     */
    public function flushFailed(): void
    {
        $sql = $this->db->createSql();
        $sql->delete($this->failedTable);

        $this->db->query($sql);
    }

    /**
     * Flush all pop worker items
     *
     * @return void
     */
    public function flushAll(): void
    {
        $this->flush(true);
        $this->flushFailed();
    }

    /**
     * Get the database object
     *
     * @return ?DbAdapter
     */
    public function db(): ?DbAdapter
    {
        return $this->db;
    }

    /**
     * Get the job table
     *
     * @return ?string
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    /**
     * Get the failed job table
     *
     * @return ?string
     */
    public function getFailedTable(): ?string
    {
        return $this->failedTable;
    }

    /**
     * Create the worker job table
     *
     * @param  string $table
     * @return Database
     */
    public function createTable(string $table): Database
    {
        $schema = $this->db->createSchema();

        $schema->create($table)
            ->int('id')->increment()
            ->varchar('job_id', 255)
            ->varchar('worker', 255)
            ->varchar('priority', 255)
            ->text('payload')
            ->int('max_attempts', 16)
            ->int('attempts', 16)
            ->datetime('completed')
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

    /**
     * Create the worker failed job table
     *
     * @param  string $failedTable
     * @return Database
     */
    public function createFailedTable(string $failedTable): Database
    {
        $schema = $this->db->createSchema();

        $schema->create($failedTable)
            ->int('id', 16)->increment()
            ->varchar('job_id', 255)
            ->varchar('worker', 255)
            ->text('payload')
            ->text('exception')
            ->datetime('failed')
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

}
