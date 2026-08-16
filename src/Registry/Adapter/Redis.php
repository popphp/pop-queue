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
namespace Pop\Queue\Registry\Adapter;

use Pop\Queue\Registry\AbstractRegistry;
use Pop\Queue\Registry\Exception;
use Pop\Queue\Registry\WorkerRecord;

/**
 * Redis registry class
 *
 * One string key per worker, "{prefix}:worker:{id}", holding the JSON
 * record.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class Redis extends AbstractRegistry
{

    /**
     * Redis object
     * @var \Redis|null
     */
    protected \Redis|null $redis = null;

    /**
     * Key prefix
     * @var string
     */
    protected string $prefix = 'pop-registry';

    /**
     * Whether this instance has already reconciled the worker index set against
     * any record keys written without it. See ensureWorkerSet().
     * @var bool
     */
    protected bool $workerSetChecked = false;

    /**
     * Constructor
     *
     * @param  string     $host
     * @param  int|string $port
     * @param  string     $prefix
     * @param  ?string    $password
     * @param  ?array     $context
     * @throws Exception|\RedisException
     */
    public function __construct(
        string $host = 'localhost', int|string $port = 6379, string $prefix = 'pop-registry',
        ?string $password = null, ?array $context = null
    )
    {
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis  = new \Redis();
        $this->prefix = $prefix;

        if (!$this->redis->connect($host, (int)$port, context: $context)) {
            throw new Exception('Error: Unable to connect to the redis server.');
        }

        if (($password !== null) && !$this->redis->auth($password)) {
            throw new Exception('Error: Unable to authenticate with the redis server.');
        }
    }

    /**
     * Get the Redis object
     *
     * @return \Redis|null
     */
    public function getRedis(): \Redis|null
    {
        return $this->redis;
    }

    /**
     * Get the key prefix
     *
     * @return string
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * The key backing a given worker ID
     *
     * @param  string $id
     * @return string
     */
    protected function recordKey(string $id): string
    {
        return $this->prefix . ':worker:' . $id;
    }

    /**
     * Key of the set indexing registered worker IDs.
     *
     * Enumerating the registry used to mean a KEYS scan, which Redis runs
     * against its whole keyspace while blocking every other client. all() is
     * called by the stuck-worker sweep, so that scan landed on a live server on
     * a routine schedule. Membership is tracked in a set instead, making the
     * enumeration one SMEMBERS against a single key.
     *
     * @return string
     */
    protected function workerSetKey(): string
    {
        return $this->prefix . ':workers';
    }

    /**
     * Bring the worker index set in line with any record keys that aren't in it,
     * once per adapter instance.
     *
     * A record key can exist outside the set two ways: it was written by a
     * version of this adapter that predates the set, or something wrote the key
     * directly. Either way the set is now the only thing all() and
     * purgeUndecodable() consult, so an unindexed record would be invisible to
     * the stuck-worker sweep and to pruning - it would sit there forever,
     * unreadable and unreapable.
     *
     * The reconciliation costs one KEYS scan per Redis database, guarded by a
     * marker key, which is the one place this adapter still issues one.
     *
     * @return void
     */
    protected function ensureWorkerSet(): void
    {
        if ($this->workerSetChecked) {
            return;
        }
        $this->workerSetChecked = true;

        if ($this->redis->exists($this->prefix . ':index-built')) {
            return;
        }

        foreach ($this->redis->keys($this->prefix . ':worker:*') as $key) {
            $id = substr($key, (strrpos($key, ':worker:') + 8));
            if ($id !== '') {
                $this->redis->sAdd($this->workerSetKey(), $id);
            }
        }

        $this->redis->set($this->prefix . ':index-built', '1');
    }

    public function write(WorkerRecord $record): void
    {
        $this->redis->set($this->recordKey($record->getId()), $this->encode($record));
        $this->redis->sAdd($this->workerSetKey(), $record->getId());
    }

    public function read(string $id): ?WorkerRecord
    {
        $value = $this->redis->get($this->recordKey($id));

        return ($value !== false) ? $this->decode($value) : null;
    }

    public function all(): array
    {
        $this->ensureWorkerSet();

        $records = [];

        foreach ($this->redis->sMembers($this->workerSetKey()) as $id) {
            $value = $this->redis->get($this->recordKey($id));
            if ($value === false) {
                // Indexed but gone - the record expired or was deleted out from
                // under the set. Drop the stale member rather than carrying it
                // forever; nothing else prunes it.
                $this->redis->sRem($this->workerSetKey(), $id);
                continue;
            }
            $record = $this->decode($value);
            if ($record !== null) {
                $records[$record->getId()] = $record;
            }
        }

        return $records;
    }

    public function delete(string $id): void
    {
        $this->redis->del($this->recordKey($id));
        $this->redis->sRem($this->workerSetKey(), $id);
    }

    protected function purgeUndecodable(): int
    {
        $this->ensureWorkerSet();

        $removed = 0;

        foreach ($this->redis->sMembers($this->workerSetKey()) as $id) {
            $value = $this->redis->get($this->recordKey($id));
            if (($value !== false) && ($this->decode($value) === null)) {
                $this->delete($id);
                $removed++;
            }
        }

        return $removed;
    }

}
