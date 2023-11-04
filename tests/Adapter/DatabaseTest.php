<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Db\Db as PopDb;
use Pop\Queue\Adapter\Database;
use Pop\Queue\Processor\Job;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{

    public function testConstructor()
    {
        touch(__DIR__ . '/../tmp/test.sqlite');
        chmod(__DIR__ . '/../tmp/test.sqlite', 0777);

        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $adapter = new Database($db);
        $this->assertInstanceOf('Pop\Queue\Adapter\Database', $adapter);
        $this->assertInstanceOf('Pop\Db\Adapter\Sqlite', $adapter->db());
        $this->assertEquals('pop_queue_jobs', $adapter->getTable());
        $this->assertEquals('pop_queue_failed_jobs', $adapter->getFailedTable());
    }

    public function testGetJobs()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

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
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

        $job   = new Job(function(){echo 'Hello World 2!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue', $job);
        $job->complete();
        $adapter->updateJob($job);

        $this->assertTrue($adapter->hasCompletedJob($jobId));
        $this->assertNotNull($adapter->getCompletedJob($jobId));
        $this->assertTrue($adapter->hasCompletedJobs('pop-queue'));
        $this->assertNotEmpty($adapter->getCompletedJobs('pop-queue'));
    }

    public function testGetFailedJobs()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);


        $job   = new Job(function(){throw new \Exception('Whoops!');});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue', $job);
        $adapter->failed('pop-queue', $jobId,  new \Exception('Whoops!'));

        $this->assertTrue($adapter->hasFailedJob($jobId));
        $this->assertNotNull($adapter->getFailedJob($jobId));
        $this->assertTrue($adapter->hasFailedJobs('pop-queue'));
        $this->assertNotEmpty($adapter->getFailedJobs('pop-queue'));
    }

    public function testPop()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

        $job   = new Job(function(){echo 'Hello World 2!';});
        $jobId = $job->generateJobId();

        $adapter->push('pop-queue', $job);
        $this->assertTrue($adapter->hasJob($jobId));
        $adapter->pop($jobId);
        $this->assertFalse($adapter->hasJob($jobId));
    }

    public function testClear()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

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
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

        $adapter->flush();
        $adapter->flush(true);
        $adapter->flushFailed();
        $adapter->flushAll();

        $this->assertFalse($adapter->hasJobs('pop-queue'));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue'));

        unlink(__DIR__ . '/../tmp/test.sqlite');
    }


}