<?php

namespace Pop\Queue\Test;

use Pop\Queue\Adapter;
use Pop\Db;
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
        $this->assertFalse($adapter->hasJob(1));
        $this->assertNull($adapter->getJob(1));
        $this->assertFalse($adapter->hasJobs('pop-queue'));
        $this->assertEmpty($adapter->getJobs('pop-queue'));
    }

    public function testGetCompletedJobs()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);
        $this->assertFalse($adapter->hasCompletedJob(1));
        $this->assertNull($adapter->getCompletedJob(1));
        $this->assertFalse($adapter->hasCompletedJobs('pop-queue'));
        $this->assertEmpty($adapter->getCompletedJobs('pop-queue'));
    }

    public function testGetFailedJobs()
    {
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);
        $this->assertFalse($adapter->hasFailedJob(1));
        $this->assertNull($adapter->getFailedJob(1));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue'));
        $this->assertEmpty($adapter->getFailedJobs('pop-queue'));

        unlink(__DIR__ . '/../tmp/test.sqlite');
    }


}