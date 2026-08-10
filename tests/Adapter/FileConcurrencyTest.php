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

}
