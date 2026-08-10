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
        $adapter1->clear();
        $adapter2->clear();
    }

    public function testPushAndReserve()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);
        $this->assertTrue($adapter->hasJobs());
        $this->assertEquals(1, $adapter->count());

        $reserved = $adapter->reserve();
        $this->assertEquals(123, $reserved->run());
        $adapter->delete($reserved);
    }

    public function testHasJobsIncludesReserved()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertTrue($adapter->hasJobs());
        $this->assertEquals(1, $adapter->count());

        $adapter->delete($reserved);
        $this->assertFalse($adapter->hasJobs());
        $adapter->clear();
    }

    public function testReserveReturnsNullWhenEmpty()
    {
        $adapter = new Redis();
        $adapter->clear();
        $this->assertNull($adapter->reserve());
    }

    public function testRelease()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);
        $reserved = $adapter->reserve();

        $adapter->release($reserved);
        $this->assertEquals(1, $adapter->count());
        $this->assertNotNull($adapter->reserve());
        $adapter->clear();
    }

    public function testBury()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);
        $reserved = $adapter->reserve();

        $adapter->bury($reserved, 'Exceeded max attempts');
        $this->assertFalse($adapter->hasJobs());
        $this->assertTrue($adapter->hasDeadJobs());
        $this->assertEquals(1, $adapter->countDead());
        $this->assertCount(1, $adapter->getDeadJobs());
        $this->assertEquals($job->getJobId(), $adapter->getDeadJob($job->getJobId())->getJobId());
        $this->assertNull($adapter->getDeadJob('does-not-exist'));

        $adapter->clearDead();
        $this->assertFalse($adapter->hasDeadJobs());
    }

    public function testRetryDeadJob()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);
        $adapter->bury($adapter->reserve(), 'reason');
        $adapter->retryDeadJob($job->getJobId());

        $this->assertFalse($adapter->hasDeadJobs());
        $this->assertTrue($adapter->hasJobs());
        $adapter->clear();
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
        $adapter->clearDead();

        $this->assertFalse($adapter->hasJobs());
        $this->assertFalse($adapter->hasDeadJobs());
    }

    public function testReserveFilo()
    {
        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });

        $adapter = new Redis();
        $adapter->clear();
        $adapter->setPriority('FILO');
        $adapter->push($job1);
        $adapter->push($job2);

        $this->assertEquals($job2->getJobId(), $adapter->reserve()->getJobId());

        $adapter->clear();
    }

}
