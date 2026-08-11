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
        $process = proc_open(['php', $script], $descriptorSpec, $pipes, null, $env);
        $this->assertNotFalse($process, 'Failed to spawn daemon signal worker process.');

        // Give the child time to boot PHP, reserve the job, and enter its
        // sleep(2) - safely before the job would finish naturally, so the
        // SIGTERM genuinely lands mid-job, not before or after it.
        sleep(1);

        proc_terminate($process);

        $stdout   = stream_get_contents($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertEquals(0, $exitCode, 'Worker process should have exited cleanly (code 0), not been killed. stderr: ' . $stderr);
        $this->assertFileExists($markerFile, 'The in-flight job should have finished and written its marker before the process exited.');
        $this->assertEquals('JOB COMPLETED', file_get_contents($markerFile));

        unlink($markerFile);
    }

}
