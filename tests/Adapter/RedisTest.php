<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\Redis;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
use PHPUnit\Framework\TestCase;

class RedisTest extends TestCase
{

    public function testConstructor()
    {
        $adapter1 = new Redis();
        $adapter2 = Redis::create();
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $adapter1);
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $adapter2);
        $this->assertInstanceOf('Redis', $adapter1->redis());
        $this->assertInstanceOf('Redis', $adapter2->getRedis());
        $this->assertEquals('pop-queue', $adapter1->getPrefix());
        $this->assertEquals('pop-queue', $adapter2->getPrefix());
        $this->assertEquals(0, $adapter1->getStart());
        $this->assertEquals(0, $adapter1->getEnd());
        $this->assertEquals(0, $adapter1->getStatus(1));
    }

    public function testPush()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->push($job);
        $this->assertTrue($adapter->hasJobs());
    }

    public function testPushFailed()
    {
        $job = Job::create(function(){
            return 123;
        });
        $job->failed();

        $adapter = new Redis();
        $adapter->setPriority('FILO');
        $adapter->push($job);
        $this->assertTrue($adapter->hasFailedJobs());
        $this->assertTrue($adapter->hasFailedJob(1));
        $this->assertCount(1, $adapter->getFailedJobs(false));
        $this->assertInstanceOf('Pop\Queue\Process\Job', $adapter->getFailedJob(1));
        $adapter->clearFailed();
        $adapter->clear();
    }

    public function testPop()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->push($job);

        $job = $adapter->pop();
        $this->assertEquals(123, $job->run());
    }

    public function testGetTask1()
    {
        $task = Task::create(function(){
            echo 'Task #1' . PHP_EOL;
        })->everyMinute();

        $adapter = new Redis();

        $adapter->schedule($task);
        $this->assertTrue($adapter->hasTasks());
        $this->assertCount(1, $adapter->getTasks());
        $this->assertEquals(1, $adapter->getTaskCount());
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
        $adapter = new Redis();
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

    public function testClearTasks()
    {
        $task = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute();
        $task->setMaxAttempts(1);
        $adapter = new Redis();
        $adapter->schedule($task);

        $this->assertTrue($adapter->hasTasks());
        $adapter->clearTasks();
        $this->assertFalse($adapter->hasTasks());
    }

    public function testClear()
    {
        $adapter = new Redis();

        $adapter->clear();
        $adapter->clearFailed();

        $this->assertFalse($adapter->hasJobs());
        $this->assertFalse($adapter->hasFailedJobs());
    }

    public function testPopFilo()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->setPriority('FILO');
        $adapter->push($job);

        $job = $adapter->pop();
        $this->assertEquals(123, $job->run());

        $adapter->clear();
        $adapter->clearFailed();
    }

}