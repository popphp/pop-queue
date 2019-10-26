<?php

namespace Pop\Queue\Test;

use Pop\Queue\Adapter;
use Pop\Queue\Processor\Jobs\Job;
use Pop\Queue\Processor\Jobs\Schedule;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{

    public function testConstructor()
    {
        $adapter = new Adapter\File(__DIR__ . '/../tmp');
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $adapter);
    }

    public function testGetJobs()
    {
        $adapter = new Adapter\File(__DIR__ . '/../tmp');
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $adapter);

        $job   = new Job(function(){echo 'Hello World!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);

        $this->assertTrue($adapter->hasJob($jobId));
        $this->assertNotNull($adapter->getJob($jobId));
        $this->assertTrue($adapter->hasJobs('pop-queue-test'));
        $this->assertNotEmpty($adapter->getJobs('pop-queue-test'));

        $adapter->flushAll();
        $adapter->removeQueueFolder('pop-queue-test');
    }

}