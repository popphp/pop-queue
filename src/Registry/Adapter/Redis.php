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

    public function write(WorkerRecord $record): void
    {
        $this->redis->set($this->recordKey($record->getId()), $this->encode($record));
    }

    public function read(string $id): ?WorkerRecord
    {
        $value = $this->redis->get($this->recordKey($id));

        return ($value !== false) ? $this->decode($value) : null;
    }

    public function all(): array
    {
        $records = [];

        foreach ($this->redis->keys($this->prefix . ':worker:*') as $key) {
            $value = $this->redis->get($key);
            if ($value === false) {
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
    }

    protected function purgeUndecodable(): int
    {
        $removed = 0;

        foreach ($this->redis->keys($this->prefix . ':worker:*') as $key) {
            $value = $this->redis->get($key);
            if (($value !== false) && ($this->decode($value) === null)) {
                $this->redis->del($key);
                $removed++;
            }
        }

        return $removed;
    }

}
