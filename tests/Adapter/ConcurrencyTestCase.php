<?php

namespace Pop\Queue\Test\Adapter;

use PHPUnit\Framework\TestCase;

abstract class ConcurrencyTestCase extends TestCase
{

    /**
     * Spawn $workerCount child processes that each call reserve() once
     * against the same target, wait for all of them, and return their
     * reported results (job ID string, or "NULL").
     *
     * @param  string $adapterName  "file" or "redis"
     * @param  string $target       folder path (file) or "host:port:prefix" (redis)
     * @param  int    $workerCount
     * @return array
     */
    protected function runConcurrentReservations(string $adapterName, string $target, int $workerCount): array
    {
        $script = __DIR__ . '/concurrent-reserve-worker.php';
        $env    = [
            'QUEUE_CONCURRENCY_ADAPTER' => $adapterName,
            'QUEUE_CONCURRENCY_TARGET'  => $target,
        ];

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

        $results = [];
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

}
