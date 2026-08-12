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
namespace Pop\Queue\Registry\Adapter;

use Pop\Db\Adapter\AbstractAdapter as DbAdapter;
use Pop\Queue\Registry\AbstractRegistry;
use Pop\Queue\Registry\WorkerRecord;

/**
 * Database registry class
 *
 * One row per worker: the ID as primary key, last_seen_at as its own column
 * so prune() is a single indexed DELETE, and the full record as JSON.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Database extends AbstractRegistry
{

    /**
     * Database adapter
     * @var ?DbAdapter
     */
    protected ?DbAdapter $db = null;

    /**
     * Table name
     * @var ?string
     */
    protected ?string $table = null;

    /**
     * Constructor
     *
     * @param DbAdapter $db
     * @param string    $table
     */
    public function __construct(DbAdapter $db, string $table = 'pop_worker_registry')
    {
        $this->db    = $db;
        $this->table = $table;

        if (!$this->db->hasTable($table)) {
            $this->createTable($table);
        }
    }

    /**
     * Get the database adapter
     *
     * @return ?DbAdapter
     */
    public function getDb(): ?DbAdapter
    {
        return $this->db;
    }

    /**
     * Get the table name
     *
     * @return ?string
     */
    public function getTable(): ?string
    {
        return $this->table;
    }

    public function write(WorkerRecord $record): void
    {
        // Upsert without relying on dialect-specific ON CONFLICT syntax:
        // delete any existing row for this ID, then insert. Each worker
        // writes only its own row, so there is no cross-worker race here.
        $this->delete($record->getId());

        $sql = $this->db->createSql();
        $sql->insert($this->table)->values([
            'id'           => ':id',
            'last_seen_at' => ':last_seen_at',
            'record'       => ':record'
        ]);

        $this->db->prepare($sql);
        $this->db->bindParams([
            'id'           => $record->getId(),
            'last_seen_at' => $record->getLastSeenAt(),
            'record'       => $this->encode($record)
        ]);
        $this->db->execute();
    }

    public function read(string $id): ?WorkerRecord
    {
        $sql = $this->db->createSql();
        $sql->select('record')->from($this->table)->where('id = :id');
        $this->db->prepare($sql);
        $this->db->bindParams(['id' => $id]);
        $this->db->execute();
        $rows = $this->db->fetchAll();

        return isset($rows[0]['record']) ? $this->decode($rows[0]['record']) : null;
    }

    public function all(): array
    {
        $sql = $this->db->createSql();
        $sql->select('record')->from($this->table);
        $this->db->query($sql);

        $records = [];
        foreach ($this->db->fetchAll() as $row) {
            $record = $this->decode($row['record'] ?? null);
            if ($record !== null) {
                $records[$record->getId()] = $record;
            }
        }

        return $records;
    }

    public function delete(string $id): void
    {
        $sql = $this->db->createSql();
        $sql->delete()->from($this->table)->where('id = :id');
        $this->db->prepare($sql);
        $this->db->bindParams(['id' => $id]);
        $this->db->execute();
    }

    public function prune(int $olderThanSeconds): int
    {
        // Read-filter-delete, matching the other three backends. A raw
        // "WHERE last_seen_at < ?" would be tempting here, but pop-db's
        // string where() parser is only reliable for simple expressions and
        // this codebase has no precedent for a string '<' - see the note in
        // this task's brief.
        $removed = 0;
        foreach ($this->all() as $id => $record) {
            if ($this->isExpired($record, $olderThanSeconds)) {
                $this->delete($id);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Create the registry table
     *
     * @param  string $table
     * @return Database
     */
    public function createTable(string $table): Database
    {
        $schema = $this->db->createSchema();

        $schema->create($table)
            ->varchar('id', 255)
            ->int('last_seen_at', 16)
            ->text('record')
            ->primary('id');

        $this->db->query($schema);

        return $this;
    }

}
