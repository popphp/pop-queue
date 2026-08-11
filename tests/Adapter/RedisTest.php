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
        $this->assertEquals($job1->getJobId(), $adapter->reserve()->getJobId());

        $adapter->clear();
    }

    public function testReserveMovesPastAlreadyReservedJobs()
    {
        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job1);
        $adapter->push($job2);

        $first = $adapter->reserve();
        $this->assertNotNull($first);
        $this->assertEquals($job1->getJobId(), $first->getJobId());

        $second = $adapter->reserve();
        $this->assertNotNull($second);
        $this->assertEquals($job2->getJobId(), $second->getJobId());

        $this->assertNull($adapter->reserve());
        $adapter->clear();
    }

    public function testReserveSkipsDelayedJob()
    {
        $job = Job::create(function(){ return 123; });
        $job->delay(60);

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);

        $this->assertTrue($adapter->hasJobs());
        $this->assertNull($adapter->reserve());
        $adapter->clear();
    }

    public function testReserveSkipsDelayedJobAndClaimsAvailableOne()
    {
        $delayed   = Job::create(function(){ return 1; });
        $available = Job::create(function(){ return 2; });
        $delayed->delay(60);

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($delayed);
        $adapter->push($available);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);
        $this->assertEquals($available->getJobId(), $reserved->getJobId());

        $adapter->clear();
    }

    public function testLeaseReclaim()
    {
        $job = Job::create(function(){ return 123; });

        // Deliberately a long lease, expired by hand rather than by sleeping:
        // an earlier version of this test used a 1-second lease and two
        // back-to-back reserve() calls, which legitimately (and correctly)
        // reclaimed the job on the second call whenever those two calls
        // straddled a one-second wall-clock boundary - real, intermittent CI
        // flakiness in the test, not in the adapter.
        $adapter = new Redis('localhost', 6379, 'pop-queue', null, 60);
        $adapter->clear();
        $adapter->push($job);

        $first = $adapter->reserve();
        $this->assertNotNull($first);

        // The claim recorded a lease (the reserved ZSET score) comfortably in
        // the future, so it is not reclaimable while it's still held.
        $reserved = $adapter->redis()->zRange('pop-queue:reserved', 0, -1);
        $this->assertCount(1, $reserved);
        $this->assertGreaterThan(time(), (int)$adapter->redis()->zScore('pop-queue:reserved', $reserved[0]));
        $this->assertNull($adapter->reserve());

        // Age the claim past its lease deterministically.
        $adapter->redis()->zAdd('pop-queue:reserved', time() - 100, $reserved[0]);

        $second = $adapter->reserve();
        $this->assertNotNull($second);
        $this->assertEquals($job->getJobId(), $second->getJobId());

        $adapter->delete($second);
    }

    public function testReleaseHonorsBackoff()
    {
        $job = Job::create(function(){ return 123; });
        $job->setBackoff(60);

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $reserved->failed();
        $adapter->release($reserved);

        $this->assertNull($adapter->reserve());
        $adapter->clear();
    }

    public function testReleaseHonorsExplicitDelayOverridingBackoff()
    {
        $job = Job::create(function(){ return 123; });
        $job->setBackoff(60);

        $adapter = new Redis();
        $adapter->clear();
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->release($reserved, 0);

        $this->assertNotNull($adapter->reserve());
        $adapter->clear();
    }

    public function testConstructorWithLeaseSeconds()
    {
        $adapter = new Redis('localhost', 6379, 'pop-queue', null, 30);
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $adapter);
    }

    public function testReleaseNoOpsWhenLeaseAlreadyReclaimed()
    {
        $job = Job::create(function(){ return 123; });

        $adapter = new Redis('localhost', 6379, 'pop-queue', null, 1); // 1-second lease
        $adapter->clear();
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);

        sleep(2);

        // Directly trigger the reclaim step - what reserve() does internally
        // as its first action - without also re-claiming the job in the same
        // call, so the job ends up sitting in pending, unclaimed by anyone.
        // This is the state a too-slow worker's later release() call can
        // race against: its lease already expired and self-healed, but it
        // doesn't know that yet.
        $reclaim = new \ReflectionMethod($adapter, 'reclaimExpiredLeases');
        $reclaim->setAccessible(true);
        $reclaim->invoke($adapter);

        $this->assertEquals(1, $adapter->count(), 'Precondition: the reclaimed job should be the only copy, sitting in pending.');

        // The original worker, unaware its lease already expired and was
        // reclaimed out from under it, finally calls release() on its stale
        // reference. This must not push a second copy on top of the one
        // already sitting in pending.
        $adapter->release($reserved);

        $this->assertEquals(1, $adapter->count(), 'release() on an already-reclaimed job must not duplicate it in pending.');

        $adapter->clear();
    }

    public function testReserveSkipsCorruptPayload()
    {
        $adapter = new Redis();
        $adapter->clear();

        // Write a corrupt/truncated payload straight into the pending list,
        // bypassing push(). push() lPushes, so this tail entry is the first
        // candidate under FIFO ordering. Same shape as a payload whose class
        // no longer exists after a deploy (which unserializes to a
        // __PHP_Incomplete_Class, not an AbstractJob).
        $adapter->redis()->lPush('pop-queue', 'not-valid-serialized-data');

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);
        $this->assertEquals($job->getJobId(), $reserved->getJobId());

        // The corrupt entry must be skipped outright, never claimed and
        // leased - a leased corrupt entry would be reclaimed on lease expiry
        // and poison every worker that reserves after it, forever.
        $this->assertContains('not-valid-serialized-data', $adapter->redis()->lRange('pop-queue', 0, -1));
        $this->assertNotContains('not-valid-serialized-data', $adapter->redis()->zRange('pop-queue:reserved', 0, -1));

        $adapter->delete($reserved);
        $adapter->clear();
    }

}
