<?php

namespace Pop\Queue\Test;

use PHPUnit\Framework\TestCase;

class WorkerDaemonSignalTest extends TestCase
{

    public function testWorkLoopFinishesInFlightJobBeforeExitingOnSigterm()
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('ext-pcntl is not loaded - real signal handling cannot be tested.');
        }

        $target = __DIR__ . '/tmp/pop-queue-daemon-signal';
        if (!file_exists($target)) {
            mkdir($target);
        }
        $markerFile = __DIR__ . '/tmp/pop-queue-daemon-signal-marker';
        if (file_exists($markerFile)) {
            unlink($markerFile);
        }

        $script = __DIR__ . '/worker-daemon-signal-worker.php';
        $env    = [
            'QUEUE_DAEMON_TARGET'      => $target,
            'QUEUE_DAEMON_MARKER_FILE' => $markerFile,
        ];

        $descriptorSpec = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $wallStart = microtime(true);
        $process   = proc_open(['php', $script], $descriptorSpec, $pipes, null, $env);
        $this->assertNotFalse($process, 'Failed to spawn daemon signal worker process.');

        // Give the child time to boot PHP, reserve the job, and enter its
        // ~2-second busy-wait - safely before the job would finish
        // naturally, so the SIGTERM genuinely lands mid-job, not before or
        // after it.
        sleep(1);

        proc_terminate($process);

        $stdout   = stream_get_contents($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode    = proc_close($process);
        $wallElapsed = microtime(true) - $wallStart;

        $this->assertEquals(0, $exitCode, 'Worker process should have exited cleanly (code 0), not been killed. stderr: ' . $stderr);
        $this->assertFileExists($markerFile, 'The in-flight job should have finished and written its marker before the process exited.');

        $markerContents        = file_get_contents($markerFile);
        [$marker, $elapsedRaw] = explode("\n", trim($markerContents), 2);

        $this->assertEquals('JOB COMPLETED', $marker);
        // The job's own busy-wait isn't interruptible by the SIGTERM the
        // way sleep() would be, so its self-measured elapsed time proves
        // the job's work ran to completion, uncut by the signal.
        $this->assertGreaterThanOrEqual(1.9, (float)$elapsedRaw, 'The job\'s self-measured busy-wait elapsed time should be ~2s, not cut short by the SIGTERM.');
        // The parent-observed wall-clock time for the whole child process
        // corroborates the same thing from outside the process.
        $this->assertGreaterThanOrEqual(1.9, $wallElapsed, 'The observed wall-clock time for the whole child process should be ~2s, not cut short by the SIGTERM.');

        unlink($markerFile);
    }

}
