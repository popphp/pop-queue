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
     * Get queue object
     *
     * @param  string $queueName
     * @return Queue
     */
    public function loadQueue($queueName)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where("queue = '" . $queueName . "'")
            ->where("completed IS NULL")
            ->limit(1);

        $this->db->query($sql);

        $row     = $this->db->fetch();
        $queue   = null;
        $payload = unserialize($row['payload']);

        if (($payload->hasProcessor()) && ($payload->getProcessor()->hasQueue())) {
            $queue = $payload->getProcessor()->getQueue();
        }

        return $queue;
    }

    /**
     * Check if queue adapter has jobs
     *
     * @param  string $queueName
     * @return boolean
     */
    public function hasJobs($queueName)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where("queue = '" . $queueName . "'" )
            ->where("completed IS NULL");

        $this->db->query($sql);

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get queue jobs
     *
     * @param  string $queueName
     * @return array
     */
    public function getJobs($queueName)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->table)
            ->where("queue = '" . $queueName . "'")
            ->where("completed IS NULL");

        $this->db->query($sql);


        $rows = $this->db->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['payload'] = unserialize($row['payload']);
        }

        return $rows;
    }

    /**
     * Check if queue adapter has failed jobs
     *
     * @param  string $queueName
     * @return boolean
     */
    public function hasFailedJobs($queueName)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where("queue = '" . $queueName . "'" );

        $this->db->query($sql);

        return (count($this->db->fetchAll()) > 0);
    }

    /**
     * Get queue jobs
     *
     * @param  string $queueName
     * @return array
     */
    public function getFailedJobs($queueName)
    {
        $sql = $this->db->createSql();
        $sql->select()->from($this->failedTable)->where("queue = '" . $queueName . "'" );

        $this->db->query($sql);

        $rows = $this->db->fetchAll();

        foreach ($rows as $i => $row) {
            $rows[$i]['payload'] = unserialize($row['payload']);
        }

        return $rows;
    }

    /**
     * Push job onto queue stack
     *
     * @param  string $queueName
     * @param  mixed  $job
     * @return void
     */
    public function push($queueName, $job)
    {
        $sql = $this->db->createSql();
        $sql->insert($this->table)->values([
            'queue'    => $queueName,
            'payload'  => serialize(clone $job),
            'attempts' => 0
        ]);

        $this->db->query($sql);
    }

    /**
     * Pop job off of queue stack
     *
     * @return void
     */
    public function pop()
    {

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
            ->varchar('queue', 255)
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
            ->varchar('queue', 255)
            ->text('payload')
            ->text('exception')
            ->datetime('failed')
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

}
