<?php
/**
 * Standalone worker process for testing Worker::workLoop()'s graceful
 * SIGTERM handling. Not a PHPUnit test.
 *
 * Runs one job whose closure busy-waits for ~2 seconds (deliberately not a
 * sleep() - PHP's sleep() returns early, with the remaining seconds, when a
 * signal is delivered during it, which would make it possible for this
 * script to "pass" even if the signal cut the job's work short) then writes
 * a completion marker file, inside a real workLoop(). The parent test
 * process sends a real SIGTERM partway through the job's busy-wait and
 * expects: the marker file proves the job's remaining code ran and wasn't
 * torn down mid-execution (not that the busy-wait itself was immune to the
 * signal - a busy-wait, unlike sleep(), simply isn't interruptible that
 * way), and this script exits with code 0 (workLoop() returned normally
 * after stop() was called by the signal handler, rather than the process
 * being torn down by the OS's default SIGTERM behavior).
 *
 * Required env vars:
 *   QUEUE_DAEMON_TARGET      = folder path for the File adapter
 *   QUEUE_DAEMON_MARKER_FILE = path to write the completion marker to
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Pop\Queue\Adapter\File;
use Pop\Queue\Queue;
use Pop\Queue\Worker;
use Pop\Queue\Process\Job;

$target     = getenv('QUEUE_DAEMON_TARGET');
$markerFile = getenv('QUEUE_DAEMON_MARKER_FILE');

$queue = Queue::create('pop-queue', new File($target));
$job   = Job::create(function() use ($markerFile) {
    $start = microtime(true);
    while ((microtime(true) - $start) < 2.0) {
        // Busy-wait, not interruptible by a delivered signal the way
        // sleep() is - this is what makes $elapsed below a trustworthy
        // proof that the job's own work ran to completion, uncut by the
        // SIGTERM the parent process sends mid-job.
    }
    $elapsed = microtime(true) - $start;
    file_put_contents($markerFile, "JOB COMPLETED\n" . $elapsed);
});
$queue->addJob($job);

$worker = Worker::create($queue);
$worker->workLoop(1);

exit(0);
