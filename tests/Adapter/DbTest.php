<?php

namespace Pop\Queue\Test;

use Pop\Db;
use Pop\Queue\Adapter;
use Pop\Queue\Processor\Jobs\Job;
use Pop\Queue\Processor\Jobs\Schedule;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{

    public function testConstructor()
    {
        touch(__DIR__ . '/../tmp/test.sqlite');
        chmod(__DIR__ . '/../tmp/test.sqlite', 0777);

        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $adapter = new Adapter\Db($db);
        $this->assertInstanceOf('Pop\Queue\Adapter\Db', $adapter);
        $this->assertInstanceOf('Pop\Db\Adapter\Sqlite', $adapter->db());
        $this->assertEquals('pop_queue_jobs', $adapter->getTable());
        $this->assertEquals('pop_queue_failed_jobs', $adapter->getFailedTable());
    }

    public function testGetJobs()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);

        $job   = new Job(function(){echo 'Hello World!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue', $job);

        $this->assertTrue($adapter->hasJob($jobId));
        $this->assertNotNull($adapter->getJob($jobId));
        $this->assertTrue($adapter->hasJobs('pop-queue'));
        $this->assertNotEmpty($adapter->getJobs('pop-queue'));
    }

    public function testGetCompletedJobs()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);

        $job   = new Job(function(){echo 'Hello World 2!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue', $job);
        $adapter->updateJob($jobId, true, true);
        $adapter->updateJob($jobId, true, 1);

        $this->assertTrue($adapter->hasCompletedJob($jobId));
        $this->assertNotNull($adapter->getCompletedJob($jobId));
        $this->assertTrue($adapter->hasCompletedJobs('pop-queue'));
        $this->assertNotEmpty($adapter->getCompletedJobs('pop-queue'));
    }

    public function testGetFailedJobs()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);


        $job   = new Job(function(){throw new \Exception('Whoops!');});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue', $job);
        $adapter->failed('pop-queue', $jobId,  new \Exception('Whoops!'));

        $this->assertTrue($adapter->hasFailedJob($jobId));
        $this->assertNotNull($adapter->getFailedJob($jobId));
        $this->assertTrue($adapter->hasFailedJobs('pop-queue'));
        $this->assertNotEmpty($adapter->getFailedJobs('pop-queue'));
    }

    public function testPushSchedule()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);

        $job   = new Job(function(){echo 'Hello World!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue', new Schedule($job));

        $this->assertTrue($adapter->hasJob($jobId));
        $this->assertNotNull($adapter->getJob($jobId));
        $this->assertTrue($adapter->hasJobs('pop-queue'));
        $this->assertNotEmpty($adapter->getJobs('pop-queue'));
    }

    public function testClear()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);

        $this->assertTrue($adapter->hasCompletedJobs('pop-queue'));
        $adapter->clear('pop-queue');
        $this->assertFalse($adapter->hasCompletedJobs('pop-queue'));
        $adapter->clear('pop-queue', true);
        $adapter->clearFailed('pop-queue');

        $this->assertFalse($adapter->hasJobs('pop-queue'));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue'));
    }

    public function testFlush()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);

        $adapter->flush();
        $adapter->flush(true);
        $adapter->flushFailed();
        $adapter->flushAll();

        $this->assertFalse($adapter->hasJobs('pop-queue'));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue'));

        unlink(__DIR__ . '/../tmp/test.sqlite');
    }


}