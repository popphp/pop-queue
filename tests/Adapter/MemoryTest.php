<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\Memory;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
use PHPUnit\Framework\TestCase;

class MemoryTest extends TestCase
{

    public function testConstructor()
    {
        $adapter = new Memory();
        $this->assertInstanceOf('Pop\Queue\Adapter\Memory', $adapter);
        $this->assertEquals('FIFO', $adapter->getPriority());
        $this->assertFalse($adapter->hasJobs());
        $this->assertEquals(0, $adapter->count());
    }

    public function testPushAndReserve()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $this->assertTrue($adapter->hasJobs());
        $this->assertEquals(1, $adapter->count());

        $reserved = $adapter->reserve();
        $this->assertEquals($job->getJobId(), $reserved->getJobId());
        $this->assertEquals(123, $reserved->run());
    }

    public function testReserveReturnsNullWhenEmpty()
    {
        $adapter = new Memory();
        $this->assertNull($adapter->reserve());
    }

    public function testReservedJobIsNotReReservedUntilLeaseExpires()
    {
        $adapter = new Memory(1); // 1-second lease
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $first = $adapter->reserve();
        $this->assertNotNull($first);
        $this->assertNull($adapter->reserve());

        sleep(2);
        $second = $adapter->reserve();
        $this->assertNotNull($second);
        $this->assertEquals($job->getJobId(), $second->getJobId());
    }

    public function testDelayedJobNotEligibleUntilDue()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $job->delay(60);
        $adapter->push($job);

        $this->assertTrue($adapter->hasJobs());
        $this->assertNull($adapter->reserve());
    }

    public function testFifoOrdering()
    {
        $adapter = new Memory();
        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });
        $adapter->push($job1);
        $adapter->push($job2);

        $this->assertEquals($job1->getJobId(), $adapter->reserve()->getJobId());
        $this->assertEquals($job2->getJobId(), $adapter->reserve()->getJobId());
    }

    public function testFiloOrdering()
    {
        $adapter = new Memory(60, 'FILO');
        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });
        $adapter->push($job1);
        $adapter->push($job2);

        $this->assertEquals($job2->getJobId(), $adapter->reserve()->getJobId());
        $this->assertEquals($job1->getJobId(), $adapter->reserve()->getJobId());
    }

    public function testReleasePutsJobBackAsPending()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->release($reserved);

        $this->assertEquals(1, $adapter->count());
        $this->assertNotNull($adapter->reserve());
    }

    public function testReleaseHonorsExplicitDelay()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->release($reserved, 60);

        $this->assertNull($adapter->reserve());
    }

    public function testReleaseHonorsJobBackoff()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $job->setBackoff(60);
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $reserved->failed();
        $adapter->release($reserved);

        $this->assertNull($adapter->reserve());
    }

    public function testDeleteRemovesJobPermanently()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->delete($reserved);

        $this->assertFalse($adapter->hasJobs());
        $this->assertEquals(0, $adapter->count());
    }

    public function testBuryMovesJobToDeadLetterStore()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->bury($reserved, 'Exceeded max attempts');

        $this->assertFalse($adapter->hasJobs());
        $this->assertTrue($adapter->hasDeadJobs());
        $this->assertEquals(1, $adapter->countDead());
        $this->assertCount(1, $adapter->getDeadJobs());
        $this->assertEquals($job->getJobId(), $adapter->getDeadJob($job->getJobId())->getJobId());
        $this->assertNull($adapter->getDeadJob('does-not-exist'));
    }

    public function testRetryDeadJobMovesItBackToPending()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->bury($reserved, 'Exceeded max attempts');
        $adapter->retryDeadJob($job->getJobId());

        $this->assertFalse($adapter->hasDeadJobs());
        $this->assertTrue($adapter->hasJobs());
        $this->assertEquals($job->getJobId(), $adapter->reserve()->getJobId());
    }

    public function testDeleteDeadJobRemovesItPermanently()
    {
        $adapter = new Memory();
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);
        $adapter->bury($adapter->reserve(), 'reason');
        $adapter->deleteDeadJob($job->getJobId());

        $this->assertFalse($adapter->hasDeadJobs());
    }

    public function testClearDead()
    {
        $adapter = new Memory();
        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });
        $adapter->push($job1);
        $adapter->push($job2);
        $adapter->bury($adapter->reserve(), 'r1');
        $adapter->bury($adapter->reserve(), 'r2');

        $adapter->clearDead();
        $this->assertFalse($adapter->hasDeadJobs());
        $this->assertEquals(0, $adapter->countDead());
    }

    public function testClearRemovesPendingAndReserved()
    {
        $adapter = new Memory();
        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });
        $adapter->push($job1);
        $adapter->push($job2);
        $adapter->reserve();

        $adapter->clear();
        $this->assertFalse($adapter->hasJobs());
        $this->assertEquals(0, $adapter->count());
    }

    public function testScheduleAndGetTasks()
    {
        $adapter = new Memory();
        $task = Task::create(function(){ echo 'Task #1'; })->everyMinute();
        $adapter->schedule($task);

        $this->assertTrue($adapter->hasTasks());
        $this->assertEquals(1, $adapter->getTaskCount());
        $this->assertCount(1, $adapter->getTasks());
        $this->assertEquals($task->getJobId(), $adapter->getTask($task->getJobId())->getJobId());
    }

    public function testGetTaskReturnsNullWhenMissing()
    {
        $adapter = new Memory();
        $this->assertNull($adapter->getTask('does-not-exist'));
    }

    public function testUpdateTask()
    {
        $adapter = new Memory();
        $task = Task::create(function(){ return 'Task #1'; })->everyMinute();
        $adapter->schedule($task);

        $task->complete();
        $adapter->updateTask($task);

        $this->assertTrue($adapter->hasTasks());
        $this->assertTrue($adapter->getTask($task->getJobId())->isComplete());
    }

    public function testUpdateTaskRemovesInvalidTask()
    {
        $adapter = new Memory();
        $task = Task::create(function(){ return 'Task #1'; })->everyMinute();
        $task->setMaxAttempts(1);
        $adapter->schedule($task);

        $task->run();
        $task->complete();
        $task->run();
        $adapter->updateTask($task);

        $this->assertFalse($adapter->hasTasks());
    }

    public function testRemoveTask()
    {
        $adapter = new Memory();
        $task = Task::create(function(){ echo 'Task #1'; })->everyMinute();
        $adapter->schedule($task);
        $adapter->removeTask($task->getJobId());

        $this->assertFalse($adapter->hasTasks());
    }

    public function testClearTasks()
    {
        $adapter = new Memory();
        $task1 = Task::create(function(){ echo 'Task #1'; })->everyMinute();
        $task2 = Task::create(function(){ echo 'Task #2'; })->every5Minutes();
        $adapter->schedule($task1);
        $adapter->schedule($task2);

        $adapter->clearTasks();
        $this->assertFalse($adapter->hasTasks());
        $this->assertEquals(0, $adapter->getTaskCount());
    }
}
