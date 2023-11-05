<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\Redis;
use Pop\Queue\Processor\Job;
use PHPUnit\Framework\TestCase;

class RedisTest extends TestCase
{

    public function testConstructor()
    {
        $adapter = new Redis();
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $adapter);
        $this->assertInstanceOf('Redis', $adapter->redis());
        $this->assertEquals('pop-queue-', $adapter->getPrefix());
    }

    public function testCreate()
    {
        $adapter = Redis::create();
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $adapter);
        $this->assertInstanceOf('Redis', $adapter->redis());
    }

    public function testGetJobs()
    {
        $adapter = new Redis();

        $job   = new Job(function(){echo 'Hello World!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);

        $this->assertTrue($adapter->hasJob($jobId));
        $this->assertNotNull($adapter->getJob($jobId));
        $this->assertTrue($adapter->hasJobs('pop-queue-test'));
        $this->assertFalse($adapter->hasJobs('pop-queue-bad'));
        $this->assertNotEmpty($adapter->getJobs('pop-queue-test'));
    }

    public function testGetQueues()
    {
        $adapter = new Redis();
        $queues = $adapter->getQueues();
        $this->assertCount(1, $queues);
        $this->assertTrue(in_array('pop-queue-test', $queues));
    }

    public function testGetCompletedJobs()
    {
        $adapter = new Redis();

        $job   = new Job(function(){echo 'Hello World 2!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);
        $job->complete();
        $adapter->updateJob($job);

        $this->assertTrue($adapter->hasCompletedJob($jobId));
        $this->assertFalse($adapter->hasCompletedJob($jobId . '-bad'));
        $this->assertNotNull($adapter->getCompletedJob($jobId));
        $this->assertEmpty($adapter->getCompletedJob($jobId . '-bad'));
        $this->assertTrue($adapter->hasCompletedJobs('pop-queue-test'));
        $this->assertFalse($adapter->hasCompletedJobs('pop-queue-bad'));
        $this->assertNotEmpty($adapter->getCompletedJobs('pop-queue-test'));
    }

    public function testGetFailedJobs()
    {
        $adapter = new Redis();

        $job   = new Job(function(){throw new \Exception('Whoops!');});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue-test', $job);
        $adapter->failed('pop-queue-test', $jobId,  new \Exception('Whoops!'));

        $this->assertTrue($adapter->hasFailedJob($jobId));
        $this->assertNotNull($adapter->getFailedJob($jobId));
        $this->assertTrue($adapter->hasFailedJobs('pop-queue-test'));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue-bad'));
        $this->assertNotEmpty($adapter->getFailedJobs('pop-queue-test'));
    }

    public function testClear()
    {
        $adapter = new Redis();

        $adapter->clear('pop-queue-test');
        $adapter->clear('pop-queue-test', true);
        $adapter->clearFailed('pop-queue-test');

        $this->assertFalse($adapter->hasJobs('pop-queue-test'));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue-test'));
    }

    public function testFlush()
    {
        $adapter = new Redis();

        $adapter->flush();
        $adapter->flush(true);
        $adapter->flushFailed();
        $adapter->flushAll();

        $this->assertFalse($adapter->hasJobs('pop-queue-test'));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue-test'));
    }

}