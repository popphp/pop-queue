<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{

    public function testConstructor()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $adapter);
        $this->assertEquals(__DIR__ . '/../tmp/pop-queue', $adapter->getFolder());
        $this->assertEquals(__DIR__ . '/../tmp/pop-queue', $adapter->folder());
        $this->assertEquals(0, $adapter->getStart());
        $this->assertEquals(0, $adapter->getStatus(0));
        $this->assertEmpty($adapter->getFolders(__DIR__ . '/../tmp/bad'));
        $this->assertEmpty($adapter->getFiles(__DIR__ . '/../tmp/bad'));
    }

    public function testConstructorException()
    {
        $this->expectException('Pop\Queue\Adapter\Exception');
        $adapter = File::create(__DIR__ . '/../tmp/bad-queue');
    }

    public function testPop()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->push($job);

        $job = $adapter->pop();
        $this->assertEquals(123, $job->run());
    }

    public function testPushFailed()
    {
        $job = Job::create(function(){
            return 123;
        });
        $job->failed();

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->push($job);

        $this->assertTrue($adapter->hasFailedJobs());
        $this->assertNull($adapter->getFailedJob(0));
        $this->assertTrue($adapter->getFailedJob(1)->hasFailed());
        $adapter->clearFailed();
        $this->assertFalse($adapter->hasFailedJobs());
        $adapter->clear();
    }

    public function testPushFailedFilo()
    {
        $job = Job::create(function(){
            return 123;
        });
        $job->failed();

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $adapter->push($job);

        $this->assertTrue($adapter->hasFailedJobs());
        $this->assertNull($adapter->getFailedJob(0));
        $this->assertTrue($adapter->getFailedJob(-1)->hasFailed());
        $adapter->clearFailed();
        $this->assertFalse($adapter->hasFailedJobs());
        $adapter->clear();
    }

    public function testGetTask1()
    {
        $task = Task::create(function(){
            echo 'Task #1' . PHP_EOL;
        })->everyMinute();

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $adapter->schedule($task);
        $this->assertTrue($adapter->hasTasks());
        $this->assertCount(1, $adapter->getTasks());
        $this->assertInstanceOf('Pop\Queue\Process\Task', $adapter->getTask($task->getJobId()));

        $task->complete();
        $adapter->updateTask($task);
        $this->assertInstanceOf('Pop\Queue\Process\Task', $adapter->getTask($task->getJobId()));
        $adapter->clear();
    }

    public function testGetTask2()
    {
        $task = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute();
        $task->setMaxAttempts(1);
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
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

}