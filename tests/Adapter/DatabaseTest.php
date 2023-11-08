<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Db\Db as PopDb;
use Pop\Queue\Adapter\Database;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
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

        $adapter1 = new Database($db);
        $adapter2 = Database::create($db);
        $this->assertInstanceOf('Pop\Queue\Adapter\Database', $adapter1);
        $this->assertInstanceOf('Pop\Queue\Adapter\Database', $adapter2);
        $this->assertInstanceOf('Pop\Db\Adapter\Sqlite', $adapter1->getDb());
        $this->assertInstanceOf('Pop\Db\Adapter\Sqlite', $adapter2->db());
        $this->assertEquals('pop_queue', $adapter1->getTable());
        $this->assertEquals('pop_queue', $adapter2->getTable());
        $this->assertEquals(0, $adapter1->getStart());
        $this->assertEquals(0, $adapter1->getEnd());
        $this->assertEquals(0, $adapter1->getStatus(1));
    }

    public function testPush()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Database($db);
        $adapter->push($job);
        $this->assertTrue($adapter->hasJobs());
    }

    public function testPushFailed()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){
            return 123;
        });
        $job->failed();

        $adapter = new Database($db);
        $adapter->setPriority('FILO');
        $adapter->push($job);
        $this->assertTrue($adapter->hasFailedJobs());
        $this->assertTrue($adapter->hasFailedJob(0));
        $this->assertCount(1, $adapter->getFailedJobs(false));
        $this->assertInstanceOf('Pop\Queue\Process\Job', $adapter->getFailedJob(0));
        $adapter->clear();
        $adapter->clearFailed();
    }

    public function testPop()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Database($db);
        $adapter->push($job);

        $job = $adapter->pop();
        $this->assertEquals(123, $job->run());
    }

    public function testGetTask1()
    {
        $task = Task::create(function(){
            echo 'Task #1' . PHP_EOL;
        })->everyMinute();

        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

        $adapter->schedule($task);
        $this->assertTrue($adapter->hasTasks());
        $this->assertCount(1, $adapter->getTasks());
        $this->assertInstanceOf('Pop\Queue\Process\Task', $adapter->getTask($task->getJobId()));

        $task->complete();
        $adapter->updateTask($task);
        $this->assertInstanceOf('Pop\Queue\Process\Task', $adapter->getTask($task->getJobId()));
        $adapter->clear();
        $adapter->clearTasks();
    }

    public function testGetTask2()
    {
        $task = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute();
        $task->setMaxAttempts(1);
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->schedule($task);
        $this->assertTrue($adapter->hasTasks());
        $this->assertCount(1, $adapter->getTasks());
        $this->assertInstanceOf('Pop\Queue\Process\Task', $adapter->getTask($task->getJobId()));

        $task->run();
        $task->complete();
        $task->run();
        $adapter->updateTask($task);
        $this->assertFalse($adapter->hasTasks());
    }

    public function testClear()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

        $adapter->clear();
        $adapter->clearFailed();

        $this->assertFalse($adapter->hasJobs('pop-queue'));
        $this->assertFalse($adapter->hasFailedJobs('pop-queue'));

        unlink(__DIR__ . '/../tmp/test.sqlite');
    }

}