<?php
/**
 * Standalone worker process for push concurrency tests. Not a PHPUnit test.
 *
 * Reads configuration from an environment variable, constructs one File
 * adapter, pushes exactly one job, and prints the pushed job's ID to
 * stdout. Parent test process spawns many copies of this concurrently via
 * proc_open() and confirms every pushed job survives (adapter ends up with
 * exactly N jobs, all reservable, with N distinct job IDs) - proving
 * push()'s index allocation is race-safe under concurrent producers.
 *
 * Required env var:
 *   QUEUE_CONCURRENCY_TARGET = folder path
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;

$target = getenv('QUEUE_CONCURRENCY_TARGET');

$adapter = new File($target);
$job     = Job::create(function(){ return 1; });
$adapter->push($job);

echo $job->getJobId();
