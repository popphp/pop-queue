<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;

class FileConcurrencyTest extends ConcurrencyTestCase
{

    public function testConcurrentReserveNeverDoubleClaims()
    {
        $folder = __DIR__ . '/../tmp/pop-queue-concurrency';
        if (!file_exists($folder)) {
            mkdir($folder);
        }

        $adapter = File::create($folder);
        $adapter->clear();

        $workerCount = 8;
        $jobs = [];
        for ($i = 0; $i < $workerCount; $i++) {
            $job = Job::create(function(){ return 1; });
            $jobs[] = $job;
            $adapter->push($job);
        }

        $results = $this->runConcurrentReservations('file', $folder, $workerCount);

        $claimed = array_filter($results, fn($r) => $r !== 'NULL');
        $this->assertCount($workerCount, $claimed, 'Every pushed job should have been claimed by exactly one worker.');
        $this->assertCount($workerCount, array_unique($claimed), 'No job should have been claimed by more than one worker.');

        foreach ($jobs as $job) {
            $this->assertContains($job->getJobId(), $claimed);
        }

        $adapter->clear();
    }

    public function testConcurrentTaskClaimNeverDoubleClaims()
    {
        $folder = __DIR__ . '/../tmp/pop-queue-task-claim-concurrency';
        if (!file_exists($folder)) {
            mkdir($folder);
        }

        $taskId      = 'concurrency-claim-task';
        $window      = '100';
        $workerCount = 8;

        $results = $this->runConcurrentTaskClaims('file', $folder, $taskId, $window, $workerCount);

        $claimed = array_filter($results, fn($r) => $r === '1');
        $this->assertCount(1, $claimed, 'Exactly one worker should have won the claim for the same task/window.');

        unlink($folder . '/claim-task-' . $taskId);
    }

}
