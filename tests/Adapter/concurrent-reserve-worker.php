<?php
/**
 * Standalone worker process for concurrency tests. Not a PHPUnit test.
 *
 * Reads configuration from environment variables, constructs one adapter,
 * calls reserve() exactly once, and prints the claimed job's ID (or the
 * literal string "NULL") to stdout. Parent test processes spawn many
 * copies of this script concurrently via proc_open() and compare their
 * stdout output to prove no two processes claimed the same job.
 *
 * Required env vars:
 *   QUEUE_CONCURRENCY_ADAPTER = "file" | "redis"
 *   QUEUE_CONCURRENCY_TARGET  = folder path (file) or "host:port:prefix" (redis)
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Pop\Queue\Adapter\File;
use Pop\Queue\Adapter\Redis;

$adapterName = getenv('QUEUE_CONCURRENCY_ADAPTER');
$target      = getenv('QUEUE_CONCURRENCY_TARGET');

$adapter = match ($adapterName) {
    'file'  => new File($target),
    'redis' => (function (string $target) {
        [$host, $port, $prefix] = explode(':', $target, 3);
        return new Redis($host, (int)$port, $prefix);
    })($target),
    default => throw new \RuntimeException('Unknown QUEUE_CONCURRENCY_ADAPTER: ' . $adapterName),
};

$job = $adapter->reserve();

echo ($job !== null) ? $job->getJobId() : 'NULL';
