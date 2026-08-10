<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\File;
use PHPUnit\Framework\TestCase;

class FilePushConcurrencyTest extends TestCase
{

    /**
     * Spawn $workerCount child processes that each push exactly one job
     * against the same target folder, wait for all of them, and return
     * their reported job IDs.
     *
     * @param  string $folder
     * @param  int    $workerCount
     * @return array
     */
    protected function runConcurrentPushes(string $folder, int $workerCount): array
    {
        $script = __DIR__ . '/concurrent-push-worker.php';
        $env    = ['QUEUE_CONCURRENCY_TARGET' => $folder];

        $processes = [];
        $pipes     = [];

        for ($i = 0; $i < $workerCount; $i++) {
            $descriptorSpec = [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $process = proc_open(['php', $script], $descriptorSpec, $procPipes, null, $env);
            if ($process === false) {
                $this->fail('Failed to spawn concurrency worker process.');
            }
            $processes[$i] = $process;
            $pipes[$i]     = $procPipes;
        }

        $results  = [];
        $failures = [];

        foreach ($processes as $i => $process) {
            $results[$i] = stream_get_contents($pipes[$i][1]);
            $stderr      = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $exitCode = proc_close($process);
            if ($exitCode !== 0) {
                $failures[] = 'worker ' . $i . ' exited with code ' . $exitCode . ': ' . $stderr;
            }
        }

        if (!empty($failures)) {
            $this->fail('Concurrency worker process(es) failed: ' . implode('; ', $failures));
        }

        return $results;
    }

    public function testConcurrentPushNeverLosesJobs()
    {
        $folder = __DIR__ . '/../tmp/pop-queue-push-concurrency';
        if (!file_exists($folder)) {
            mkdir($folder);
        }

        $adapter = File::create($folder);
        $adapter->clear();

        $workerCount = 8;
        $pushedIds   = $this->runConcurrentPushes($folder, $workerCount);

        $this->assertCount($workerCount, array_unique($pushedIds), 'Every worker should have pushed a distinct job ID.');
        $this->assertEquals($workerCount, $adapter->count(), 'No pushed job should have been silently clobbered by another concurrent push.');

        $reservedIds = [];
        for ($i = 0; $i < $workerCount; $i++) {
            $reserved = $adapter->reserve();
            $this->assertNotNull($reserved);
            $reservedIds[] = $reserved->getJobId();
        }

        $this->assertCount($workerCount, array_unique($reservedIds));
        $this->assertEqualsCanonicalizing($pushedIds, $reservedIds);

        $adapter->clear();
    }

}
