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
        $this->assertEmpty($adapter->getFolders(__DIR__ . '/../tmp/bad'));
        $this->assertEmpty($adapter->getFiles(__DIR__ . '/../tmp/bad'));
    }

    public function testConstructorException()
    {
        $this->expectException('Pop\Queue\Adapter\Exception');
        $adapter = File::create(__DIR__ . '/../tmp/bad-queue');
    }

    public function testPushAndReserve()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->push($job);

        $this->assertTrue($adapter->hasJobs());
        $this->assertEquals(1, $adapter->count());

        $job = $adapter->reserve();
        $this->assertEquals(123, $job->run());
        $adapter->delete($job);
        $adapter->clear();
    }

    public function testReserveReturnsNullWhenEmpty()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $this->assertNull($adapter->reserve());
    }

    public function testRelease()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
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

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
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

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->push($job);
        $adapter->bury($adapter->reserve(), 'reason');
        $adapter->retryDeadJob($job->getJobId());

        $this->assertFalse($adapter->hasDeadJobs());
        $this->assertTrue($adapter->hasJobs());
        $adapter->clear();
    }

    public function testDeleteDeadJob()
    {
        $job = Job::create(function(){
            return 123;
        });

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->push($job);
        $adapter->bury($adapter->reserve(), 'reason');
        $adapter->deleteDeadJob($job->getJobId());

        $this->assertFalse($adapter->hasDeadJobs());
    }

    public function testPushBeyondTenSlotsDoesNotOverwrite()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $jobIds = [];
        for ($i = 1; $i <= 11; $i++) {
            $job = Job::create(function() use ($i) { return $i; });
            $adapter->push($job);
            $jobIds[] = $job->getJobId();
        }

        // Folder names sort alphabetically ('10' < '2'), so a lexicographic
        // end index would reuse an existing slot and drop jobs
        $this->assertEquals(11, $adapter->count());

        $reservedIds = [];
        for ($i = 1; $i <= 11; $i++) {
            $reserved = $adapter->reserve();
            $this->assertNotNull($reserved);
            $reservedIds[] = $reserved->getJobId();
        }

        $this->assertCount(11, array_unique($reservedIds));
        $this->assertEquals($jobIds, $reservedIds);
        $this->assertNull($adapter->reserve());

        $adapter->clear();
    }

    public function testReserveSkipsDelayedJob()
    {
        $job = Job::create(function(){ return 123; });
        $job->delay(60);

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();
        $adapter->push($job);

        $this->assertTrue($adapter->hasJobs());
        $this->assertNull($adapter->reserve());
        $adapter->clear();
    }

    public function testClearDoesNotTouchTasksOrDeadJobs()
    {
        $job  = Job::create(function(){ return 123; });
        $task = Task::create(function(){ echo 'Task #1'; })->everyMinute();

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->push($job);
        $adapter->schedule($task);
        $adapter->bury($adapter->reserve(), 'reason');

        $adapter->clear();

        $this->assertTrue($adapter->hasTasks());
        $this->assertTrue($adapter->hasDeadJobs());

        $adapter->clearTasks();
        $adapter->clearDead();
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
        $adapter->clearTasks();
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
