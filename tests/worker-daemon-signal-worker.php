<?php
/**
 * Standalone worker process for testing Worker::workLoop()'s graceful
 * SIGTERM handling. Not a PHPUnit test.
 *
 * Runs one job whose closure sleeps briefly then writes a completion
 * marker file, inside a real workLoop(). The parent test process sends a
 * real SIGTERM partway through the job's sleep and expects: the marker
 * file proves the job finished completely before the process exited (not
 * killed mid-job), and this script exits with code 0 (workLoop() returned
 * normally after stop() was called by the signal handler, rather than the
 * process being torn down by the OS's default SIGTERM behavior).
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
    sleep(2);
    file_put_contents($markerFile, 'JOB COMPLETED');
});
$queue->addJob($job);

$worker = Worker::create($queue);
$worker->workLoop(1);

exit(0);
