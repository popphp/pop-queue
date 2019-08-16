<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Adapter;

use Pop\Db\Adapter\AbstractAdapter as DbAdapter;
use Pop\Queue\Queue;
use Pop\Queue\Processor\Jobs;

/**
 * Database queue adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Db extends AbstractAdapter
{

    /**
     * Database adapter
     * @var DbAdapter
     */
    protected $db = null;

    /**
     * Job table
     * @var string
     */
    protected $table = null;

    /**
     * Failed job table
     * @var string
     */
    protected $failedTable = null;

    /**
     * Constructor
     *
     * Instantiate the database queue object
     *
     * @param DbAdapter $db
     * @param string    $table
     * @param string    $failedTable
     */
    public function __construct(DbAdapter $db, $table = 'pop_queue_jobs', $failedTable = 'pop_queue_failed_jobs')
    {
        $this->db    = $db;
        $this->table = $table;

        if (!$this->db->hasTable($table)) {
            $this->createTable($table);
        }
        if (!$this->db->hasTable($failedTable)) {
            $this->createFailedTable($failedTable);
        }
    }

    /**
     * Check if queue stack has job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasJob($jobId)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @return array
     */
    public function getJob($jobId)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)->where('job_id = :job_id')->limit(1);

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        $rows = $this->db->fetchAll();
        return (isset($rows[0])) ? $rows[0] : null;
    }

    /**
     * Update job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @param  mixed $job
     * @param  mixed $completed
     * @param  mixed $increment
     * @return void
     */
    public function updateJob($jobId, $job, $completed = false, $increment = false)
    {
        $jobRecord = $this->getJob($jobId);
        $values    = ['payload' => ':payload'];
        $params    = ['payload' => serialize(clone $job)];

        if ($completed !== false) {
            $values['completed'] = ':completed';
            $params['completed'] = ($completed === true) ? date('Y-m-d H:i:s') : $completed;
        }
        if ($increment !== false) {
            $values['attempts'] = ':attempts';
            $params['attempts'] = ($increment === true) && isset($jobRecord['attempts']) ?
                $jobRecord['attempts']++ : (int)$increment;
        }

        $params['job_id'] = $jobId;

        $sql = $this->db->createSql();
        $sql->update($this->table)->values($values)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams($params);
        $this->db->execute();
    }

    /**
     * Check if queue has jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('queue = :queue')
            ->where('completed IS NULL');

        $this->db->prepare($sql);
        $this->db->bindParams(['queue' => $queueName]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('queue = :queue')
            ->where('completed IS NULL');

        $this->db->prepare($sql);
        $this->db->bindParams(['queue' => $queueName]);
        $this->db->execute();

        $rows = $this->db->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['payload'] = unserialize($row['payload']);
        }

        return $rows;
    }

    /**
     * Check if queue has completed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasCompletedJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('queue = :queue')
            ->where('completed IS NOT NULL');

        $this->db->prepare($sql);
        $this->db->bindParams(['queue' => $queueName]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get queue completed jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getCompletedJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where('queue = :queue')
            ->where('completed IS NOT NULL');

        $this->db->prepare($sql);
        $this->db->bindParams(['queue' => $queueName]);
        $this->db->execute();

        $rows = $this->db->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['payload'] = unserialize($row['payload']);
        }

        return $rows;
    }

    /**
     * Check if queue stack has failed job
     *
     * @param  mixed $jobId
     * @return boolean
     */
    public function hasFailedJob($jobId)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get failed job from queue stack by job ID
     *
     * @param  mixed $jobId
     * @return array
     */
    public function getFailedJob($jobId)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where('job_id = :job_id')->limit(1);

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();

        $rows = $this->db->fetchAll();
        return (isset($rows[0])) ? $rows[0] : null;
    }

    /**
     * Update failed job from queue stack by job ID
     *
     * @param  mixed      $jobId
     * @param  mixed      $failedJob
     * @param  mixed      $failed
     * @param  \Exception $exception
     * @return void
     */
    public function updateFailedJob($jobId, $failedJob, $failed = false, \Exception $exception = null)
    {
        $values = ['payload' => ':payload'];
        $params = ['payload' => serialize(clone $failedJob)];

        if ($failed !== false) {
            $values['failed'] = ':failed';
            $params['failed'] = ($failed === true) ? date('Y-m-d H:i:s') : $failed;
        }
        if (null !== $exception) {
            $values['exception'] = ':exception';
            $params['exception'] = $exception->getMessage();
        }

        $params['job_id'] = $jobId;

        $sql = $this->db->createSql();
        $sql->update($this->failedTable)->values($values)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams($params);
        $this->db->execute();
    }

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  mixed $queue
     * @return boolean
     */
    public function hasFailedJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where('queue = :queue');

        $this->db->prepare($sql);
        $this->db->bindParams(['queue' => $queueName]);
        $this->db->execute();

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get queue jobs
     *
     * @param  mixed $queue
     * @return array
     */
    public function getFailedJobs($queue)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where("queue = :queue");

        $this->db->prepare($sql);
        $this->db->bindParams(['queue' => $queueName]);
        $this->db->execute();

        $rows = $this->db->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['payload'] = unserialize($row['payload']);
        }

        return $rows;
    }

    /**
     * Push job onto queue stack
     *
     * @param  mixed $queue
     * @param  mixed $job
     * @param  mixed $priority
     * @return void
     */
    public function push($queue, $job, $priority = null)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $jobId     = null;

        $sql = $this->db->createSql();
        $sql->insert($this->table)->values([
            'job_id'    => ':job_id',
            'queue'     => ':queue',
            'payload'   => ':payload',
            'priority'  => ':priority',
            'attempts'  => ':attempts',
            'completed' => ':completed'
        ]);

        if ($job instanceof Jobs\Schedule) {
            $jobId = ($job->getJob()->hasJobId()) ? $job->getJob()->getJobId() :$job->getJob()->generateJobId();
        } else if ($job instanceof Jobs\Job) {
            $jobId = ($job->hasJobId()) ? $job->getJobId() : $job->generateJobId();
        }

        $this->db->prepare($sql);
        $this->db->bindParams([
            'job_id'    => $jobId,
            'queue'     => $queueName,
            'payload'   => serialize(clone $job),
            'priority'  => $priority,
            'attempts'  => 0,
            'completed' => null
        ]);

        $this->db->execute();
    }

    /**
     * Move failed job to failed queue stack
     *
     * @param  mixed      $queue
     * @param  mixed      $failedJob
     * @param  \Exception $exception
     * @return void
     */
    public function failed($queue, $failedJob, \Exception $exception = null)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;
        $jobId     = null;

        $sql = $this->db->createSql();
        $sql->insert($this->failedTable)->values([
            'job_id'    => ':job_id',
            'queue'     => ':queue',
            'payload'   => ':payload',
            'exception' => ':exception',
            'failed'    => ':failed'
        ]);

        if ($failedJob instanceof Jobs\Schedule) {
            $jobId = ($failedJob->getJob()->hasJobId()) ? $failedJob->getJob()->getJobId() :$failedJob->getJob()->generateJobId();
        } else if ($failedJob instanceof Jobs\Job) {
            $jobId = ($failedJob->hasJobId()) ? $failedJob->getJobId() : $failedJob->generateJobId();
        }

        $this->db->prepare($sql);
        $this->db->bindParams([
            'job_id'    => $jobId,
            'queue'     => $queueName,
            'payload'   => serialize(clone $failedJob),
            'exception' => (null !== $exception) ? $exception->getMessage() : null,
            'failed'    => date('Y-m-d H:i:s')
        ]);

        $this->db->execute();

        if (!empty($jobId)) {
            $this->pop($jobId);
        }
    }

    /**
     * Pop job off of queue stack
     *
     * @param  mixed $jobId
     * @return void
     */
    public function pop($jobId)
    {
        $sql = $this->db->createSql();
        $sql->delete($this->table)->where('job_id = :job_id');

        $this->db->prepare($sql);
        $this->db->bindParams(['job_id' => $jobId]);
        $this->db->execute();
    }

    /**
     * Clear jobs off of the queue stack
     *
     * @param  mixed   $queue
     * @param  boolean $all
     * @return void
     */
    public function clear($queue, $all = false)
    {
        $queueName = ($queue instanceof Queue) ? $queue->getName() : $queue;

        $sql = $this->db->createSql();
        $sql->delete($this->table)
            ->where('queue = :queue');

        if (!$all) {
            $sql->delete()->where('completed IS NOT NULL');
        }

        $this->db->prepare($sql);
        $this->db->bindParams(['queue' => $queueName]);
        $this->db->execute();
    }

    /**
     * Flush all jobs off of the queue stack
     *
     * @param  boolean $all
     * @return void
     */
    public function flush($all = false)
    {
        $sql = $this->db->createSql();
        $sql->delete($this->table);

        if (!$all) {
            $sql->delete()->where('completed IS NOT NULL');
        }

        $this->db->query($sql);
    }

    /**
     * Get the database object
     *
     * @return DbAdapter
     */
    public function db()
    {
        return $this->db;
    }

    /**
     * Get the job table
     *
     * @return string
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get the failed job table
     *
     * @return string
     */
    public function getFailedTable()
    {
        return $this->failedTable;
    }

    /**
     * Create the queue job table
     *
     * @param  string $table
     * @return Db
     */
    public function createTable($table)
    {
        $schema = $this->db->createSchema();

        $schema->create($table)
            ->int('id', 16)->increment()
            ->varchar('job_id', 255)
            ->varchar('queue', 255)
            ->varchar('priority', 255)
            ->text('payload')
            ->int('attempts', 16)
            ->datetime('completed')
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

    /**
     * Create the queue failed job table
     *
     * @param  string $failedTable
     * @return Db
     */
    public function createFailedTable($failedTable)
    {
        $schema = $this->db->createSchema();

        $schema->create($failedTable)
            ->int('id', 16)->increment()
            ->varchar('job_id', 255)
            ->varchar('queue', 255)
            ->text('payload')
            ->text('exception')
            ->datetime('failed')
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

}
