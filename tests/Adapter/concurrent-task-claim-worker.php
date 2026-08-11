<?php
/**
 * Standalone worker process for task-claim concurrency tests. Not a
 * PHPUnit test.
 *
 * Reads configuration from environment variables, constructs one adapter,
 * calls claimTaskRun() exactly once for the given task ID and window, and
 * prints "1" (claimed) or "0" (did not claim) to stdout. Parent test
 * processes spawn many copies of this script concurrently via proc_open()
 * and compare their stdout output to prove exactly one claimed.
 *
 * Required env vars:
 *   QUEUE_CONCURRENCY_ADAPTER = "file" | "redis"
 *   QUEUE_CONCURRENCY_TARGET  = folder path (file) or "host:port:prefix" (redis)
 *   QUEUE_CONCURRENCY_TASK_ID = task ID to claim
 *   QUEUE_CONCURRENCY_WINDOW  = window value to claim
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Pop\Queue\Adapter\File;
use Pop\Queue\Adapter\Redis;

$adapterName = getenv('QUEUE_CONCURRENCY_ADAPTER');
$target      = getenv('QUEUE_CONCURRENCY_TARGET');
$taskId      = getenv('QUEUE_CONCURRENCY_TASK_ID');
$window      = getenv('QUEUE_CONCURRENCY_WINDOW');

$adapter = match ($adapterName) {
    'file'  => new File($target),
    'redis' => (function (string $target) {
        [$host, $port, $prefix] = explode(':', $target, 3);
        return new Redis($host, (int)$port, $prefix);
    })($target),
    default => throw new \RuntimeException('Unknown QUEUE_CONCURRENCY_ADAPTER: ' . $adapterName),
};

echo $adapter->claimTaskRun($taskId, $window) ? '1' : '0';
