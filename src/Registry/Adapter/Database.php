<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
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
 * so the table is directly queryable by an operator, and the full record as
 * JSON.
 *
 * prune() is inherited as a read-filter-delete loop rather than overridden
 * with a bulk DELETE: pop-db's string where() parser is only reliable for
 * simple expressions and this codebase has no precedent for a string '<'
 * comparison (src/Adapter/Database.php uses the lessThanOrEqualTo()
 * predicate API wherever it needs one). A registry holds tens of rows, so
 * this costs nothing real.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
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

    protected function purgeUndecodable(): int
    {
        $sql = $this->db->createSql();
        $sql->select(['id', 'record'])->from($this->table);
        $this->db->query($sql);

        $bad = [];
        foreach ($this->db->fetchAll() as $row) {
            if ($this->decode($row['record'] ?? null) === null) {
                $bad[] = (string)$row['id'];
            }
        }

        foreach ($bad as $id) {
            $this->delete($id);
        }

        return count($bad);
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
