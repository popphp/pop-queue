<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\File;
use Pop\Queue\Processor\Job;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{

    public function testConstructor()
    {
        $adapter = new File(__DIR__ . '/../tmp');
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $adapter);
        $this->assertStringContainsString('/tmp', $adapter->folder());
    }

    public function testCreate()
    {
        $adapter = File::create(__DIR__ . '/../tmp');
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $adapter);
        $this->assertStringContainsString('/tmp', $adapter->folder());
    }

    public function testConstructorException()
    {
        $this->expectException('Pop\Queue\Adapter\Exception');
        $adapter = new File(__DIR__ . '/../bad');
    }

    public function testGetJobs()
    {
        $adapter = new File(__DIR__ . '/../tmp');

        $job   = new Job(function(){echo 'Hello World!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);

        $this->assertTrue($adapter->hasJob($jobId));
        $this->assertNotNull($adapter->getJob($jobId));
        $this->assertTrue($adapter->hasJobs('pop-queue-test'));
        $this->assertNotEmpty($adapter->getJobs('pop-queue-test'));
    }

    public function testGetCompletedJobs1()
    {
        $adapter = new File(__DIR__ . '/../tmp');

        $job   = new Job(function(){echo 'Hello World 2!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);
        $job->complete();
        $adapter->updateJob($job);

        $this->assertTrue($adapter->hasCompletedJob($jobId));
        $this->assertNotNull($adapter->getCompletedJob($jobId));
        $this->assertTrue($adapter->hasCompletedJobs('pop-queue-test'));
        $this->assertNotEmpty($adapter->getCompletedJobs('pop-queue-test'));
    }

    public function testGetCompletedJobs2()
    {
        $adapter = new File(__DIR__ . '/../tmp');

        $job   = new Job(function(){echo 'Hello World 2!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);
        $job->complete();
        $adapter->updateJob($job);

        $this->assertTrue($adapter->hasCompletedJob($jobId));
        $this->assertNotNull($adapter->getCompletedJob($jobId));
        $this->assertTrue($adapter->hasCompletedJobs('pop-queue-test'));
        $this->assertNotEmpty($adapter->getCompletedJobs('pop-queue-test'));
    }

    public function testGetFailedJobs()
    {
        $adapter = new File(__DIR__ . '/../tmp');

        $job   = new Job(function(){throw new \Exception('Whoops!');});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);
        $job->failed();
        $adapter->failed('pop-queue-test', $jobId,  new \Exception('Whoops!'));

        $this->assertTrue($adapter->hasFailedJob($jobId));
        $this->assertNotNull($adapter->getFailedJob($jobId));
        $this->assertTrue($adapter->hasFailedJobs('pop-queue-test'));
        $this->assertNotEmpty($adapter->getFailedJobs('pop-queue-test'));

        $adapter->flushAll();
        $adapter->removeWorkerFolder('pop-queue-test');
    }

}