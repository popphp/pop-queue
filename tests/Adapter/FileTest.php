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

    public function testLeaseReclaim()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', null, 1); // 1-second lease
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $first = $adapter->reserve();
        $this->assertNotNull($first);
        $this->assertNull($adapter->reserve());

        sleep(2);

        $second = $adapter->reserve();
        $this->assertNotNull($second);
        $this->assertEquals($job->getJobId(), $second->getJobId());

        $adapter->delete($second);
    }

    public function testReleaseHonorsBackoff()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $job = Job::create(function(){ return 123; });
        $job->setBackoff(60);
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $reserved->failed();
        $adapter->release($reserved);

        $this->assertNull($adapter->reserve());
        $adapter->clear();
    }

    public function testReleaseHonorsExplicitDelayOverridingBackoff()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $job = Job::create(function(){ return 123; });
        $job->setBackoff(60);
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->release($reserved, 0);

        $this->assertNotNull($adapter->reserve());
        $adapter->clear();
    }

    public function testConstructorWithLeaseSeconds()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', null, 30);
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $adapter);
    }

    public function testReclaimHandlesCrashBeforeLeaseWrite()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', null, 1); // 1-second lease
        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $first = $adapter->reserve();
        $this->assertNotNull($first);

        // Simulate a worker crashing after the atomic rename() claimed the
        // job but before it wrote the lease file.
        $reservedDirs = $adapter->getFolders($adapter->getFolder() . '/reserved');
        $this->assertCount(1, $reservedDirs);
        $leaseFile = $adapter->getFolder() . '/reserved/' . $reservedDirs[0] . '/lease';
        $this->assertFileExists($leaseFile);
        unlink($leaseFile);

        // No lease file yet is not immediately expired - reclaim falls back
        // to the reserved directory's own age, so give it time to age past
        // the 1-second lease window.
        sleep(2);

        $second = $adapter->reserve();
        $this->assertNotNull($second);
        $this->assertEquals($job->getJobId(), $second->getJobId());

        $adapter->delete($second);
    }

    public function testReserveSkipsCorruptPayload()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        // Manually create a pending slot with a corrupt/truncated payload,
        // bypassing push() entirely, so it sorts before a valid job pushed
        // afterward under FIFO ordering.
        $corruptDir = $adapter->getFolder() . '/pending/1';
        mkdir($corruptDir);
        file_put_contents($corruptDir . '/payload', 'not-valid-serialized-data');

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);
        $this->assertEquals($job->getJobId(), $reserved->getJobId());

        $adapter->delete($reserved);
        $adapter->clear();
    }

    public function testReserveDoesNotReclaimAFreshlyTouchedReservedDirWithNoLeaseFileYet()
    {
        // Directly construct the exact state reserve() leaves behind for the
        // brief instant between its claiming rename() + touch() and its
        // lease file write completing (or the state left behind if a worker
        // crashes in that same instant): a reserved job directory with a
        // fresh mtime and no lease file yet. isLeaseExpired()'s mtime
        // fallback must treat this as "not expired" - reclaiming it here
        // would tear an active (or just-completed) claim away from its
        // worker. This is the other half of the guarantee
        // testReclaimHandlesCrashBeforeLeaseWrite covers (which proves an
        // *old*, stale mtime eventually IS reclaimed); together they pin
        // down both directions of isLeaseExpired()'s mtime-fallback
        // behavior, the exact logic reserve()'s touch() call and the shared
        // isLeaseExpired() helper (used by both the initial reclaim scan and
        // the staging re-verify step) depend on staying correct and in sync.
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', null, 5); // 5-second lease
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
        $job->getJobId();

        $reservedDir = $adapter->getFolder() . '/reserved/1';
        mkdir($reservedDir);
        file_put_contents($reservedDir . '/payload', serialize(clone $job));
        touch($reservedDir); // pin mtime to "now" - what reserve()'s touch() call guarantees at claim time
        // Deliberately no lease file - the ambiguous in-flight/crash window.

        $this->assertNull(
            $adapter->reserve(),
            'A job whose reserved directory was just touched must not be reclaimed within the lease window, even with no lease file yet.'
        );

        // Confirm it's still genuinely held in reserved/, not silently
        // bounced back to pending/ and re-claimed as something else.
        $this->assertCount(1, $adapter->getFolders($adapter->getFolder() . '/reserved'));
        $this->assertCount(0, $adapter->getFolders($adapter->getFolder() . '/pending'));

        unlink($reservedDir . '/payload');
        rmdir($reservedDir);
    }

    public function testReserveDoesNotReclaimAFreshlyTouchedReservedDirEvenWithAStaleExpiredLeaseFile()
    {
        // A job that was previously release()d or reclaimed carries its old
        // lease file back into pending/ untouched (release() only overwrites
        // 'payload', reclaim only moves the directory) - so the very next
        // time it's claimed, the resulting reserved directory starts out
        // holding a brand new mtime (from reserve()'s touch()) right next to
        // a stale, already-expired lease file, until reserve()'s own
        // subsequent lease write overwrites it moments later. This
        // constructs exactly that intermediate state directly (a real
        // sequential push()/reserve()/release()/reserve() cycle can't
        // reproduce it in a test process, since reserve() always finishes by
        // overwriting the lease file with a fresh value before returning,
        // which masks the bug regardless of fix state) and proves
        // isLeaseExpired() must treat the fresh mtime as authoritative,
        // never letting a stale lease file override it.
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', null, 5); // 5-second lease
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
        $job->getJobId();

        $reservedDir = $adapter->getFolder() . '/reserved/1';
        mkdir($reservedDir);
        file_put_contents($reservedDir . '/payload', serialize(clone $job));
        // Stale, already-expired lease file carried over from a prior claim.
        file_put_contents($reservedDir . '/lease', (string)(time() - 100));
        touch($reservedDir); // pin mtime to "now" - what reserve()'s touch() call guarantees at claim time

        $this->assertNull(
            $adapter->reserve(),
            'A freshly touched claim must not be reclaimed just because a stale expired lease file is still sitting in its directory.'
        );

        $this->assertCount(1, $adapter->getFolders($adapter->getFolder() . '/reserved'));
        $this->assertCount(0, $adapter->getFolders($adapter->getFolder() . '/pending'));

        unlink($reservedDir . '/payload');
        unlink($reservedDir . '/lease');
        rmdir($reservedDir);
    }

    public function testPushThrowsRatherThanHangsWhenPendingDirNotWritable()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $pendingDir = $adapter->getFolder() . '/pending';
        chmod($pendingDir, 0555); // read + execute, no write - mkdir() must fail

        try {
            $this->expectException('Pop\Queue\Adapter\Exception');
            $adapter->push(Job::create(function(){ return 123; }));
        } finally {
            chmod($pendingDir, 0755);
        }
    }

}
