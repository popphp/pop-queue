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
use Pop\Queue\Processor\Jobs\AbstractJob;

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
     * Push job onto queue stack
     *
     * @param  AbstractJob $job
     * @return void
     */
    public function push(AbstractJob $job)
    {

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
            ->text('queue')
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
            ->text('queue')
            ->text('payload')
            ->text('exception')
            ->datetime('failed')
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

}
