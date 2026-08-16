<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
use Pop\Queue\Process\PayloadSigner;
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

    protected function tearDown(): void
    {
        PayloadSigner::setKey(null);
    }

    public function testPushAndReserveWithSigningKeyConfigured()
    {
        PayloadSigner::setKey('test-secret-key');

        $job = Job::create(function(){
            return 123;
        });

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);
        $this->assertEquals(123, $reserved->run());

        $adapter->delete($reserved);
        $adapter->clear();
    }

    public function testReserveSkipsTamperedPayloadWhenSigningKeyConfigured()
    {
        PayloadSigner::setKey('test-secret-key');

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        // Directly corrupt the stored (signed) payload on disk, bypassing
        // the adapter entirely - simulates an attacker (or bit rot)
        // tampering with storage the adapter doesn't otherwise trust.
        $payloadFile = $adapter->getFolder() . '/pending/1/payload';
        $stored      = file_get_contents($payloadFile);
        $lastChar    = substr($stored, -1);
        $flipped     = chr((ord($lastChar) + 1) % 256);
        file_put_contents($payloadFile, substr($stored, 0, -1) . $flipped);

        $reserved = $adapter->reserve();
        $this->assertNull($reserved);

        $adapter->clear();
    }

    public function testReserveRejectsUnsignedPayloadWhenSigningKeyConfigured()
    {
        // Push while unkeyed, so the stored payload has no signature at all
        // - the real "an attacker without the key, or a payload queued
        // before your app adopted signing" case. This is what the tamper
        // test above doesn't cover: it proves reserve() actually calls
        // PayloadSigner::verify() at all, not just that it tolerates
        // garbage bytes the way plain unserialize() already did before this
        // feature existed.
        PayloadSigner::setKey(null);

        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        PayloadSigner::setKey('test-secret-key');

        $reserved = $adapter->reserve();
        $this->assertNull($reserved);

        $adapter->clear();
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
        // Deliberately a long lease, expired by hand rather than by sleeping:
        // an earlier version of this test used a 1-second lease and two
        // back-to-back reserve() calls, which legitimately (and correctly)
        // reclaimed the job on the second call whenever those two calls
        // straddled a one-second wall-clock boundary - real, intermittent CI
        // flakiness in the test, not in the adapter.
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', null, 60);
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $first = $adapter->reserve();
        $this->assertNotNull($first);

        $reservedDirs = $adapter->getFolders($adapter->getFolder() . '/reserved');
        $this->assertCount(1, $reservedDirs);
        $reservedDir = $adapter->getFolder() . '/reserved/' . $reservedDirs[0];

        // The claim recorded a lease comfortably in the future, so it is not
        // reclaimable while it's still held.
        $this->assertGreaterThan(time(), (int)file_get_contents($reservedDir . '/lease'));
        $this->assertNull($adapter->reserve());

        // Age the claim past its lease deterministically, across both signals
        // isLeaseExpired() consults: the lease file and the directory mtime.
        $expired = time() - 100;
        file_put_contents($reservedDir . '/lease', (string)$expired);
        touch($reservedDir, $expired);

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

    public function testReserveAbandonsClaimWhenReservedDirVanishesBeforeLeaseWrite()
    {
        // reserve() has a narrow window between its claiming rename() winning
        // and the touch()/lease write that follows. A concurrent reclaim can
        // move reserved/<index> away inside that window - and touch() on a
        // now-vacant path would create a stray *regular file* there, which
        // would make that index permanently unclaimable (rename() into it can
        // never succeed again) and invisible to clear() (which only walks real
        // job directories). reserve() must instead notice the directory is
        // gone and treat it as an ordinary lost race.
        //
        // The window is only observable from inside reserve(), so this
        // overrides the claim step to perform the real rename and then
        // immediately simulate a concurrent reclaim putting the directory
        // back in pending/ - exactly the state a real reclaim leaves behind.
        $adapter = new class(__DIR__ . '/../tmp/pop-queue') extends File {
            public function claimPendingDir(string $pendingDir, string $reservedDir): bool
            {
                if (!parent::claimPendingDir($pendingDir, $reservedDir)) {
                    return false;
                }
                rename($reservedDir, $pendingDir);
                return true;
            }
        };
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $this->assertNull(
            $adapter->reserve(),
            'A claim whose reserved directory vanished before the lease write must be abandoned, not completed.'
        );

        $this->assertFileDoesNotExist(
            $adapter->getFolder() . '/reserved/1',
            'reserve() must not leave a stray file where the reclaimed job directory used to be.'
        );

        // The job itself is untouched and still claimable by a normal adapter -
        // a clean lost race, not a lost job.
        $plain    = File::create(__DIR__ . '/../tmp/pop-queue');
        $reserved = $plain->reserve();
        $this->assertNotNull($reserved);
        $this->assertEquals($job->getJobId(), $reserved->getJobId());

        $plain->delete($reserved);
        $plain->clear();
    }

    public function testReserveSkipsAnIndexBlockedByANonDirectoryInReserved()
    {
        // The degraded state the guard above prevents from ever being created
        // (and which an older build could leave behind): a plain file sitting
        // at reserved/<index>. reserve() must skip over it cleanly - the
        // claiming rename() simply fails - and keep serving the rest of the
        // queue rather than erroring out.
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $blockedDir = $adapter->getFolder() . '/pending/1';
        mkdir($blockedDir);
        $job = Job::create(function(){ return 123; });
        $job->getJobId();
        file_put_contents($blockedDir . '/payload', serialize(clone $job));
        file_put_contents($adapter->getFolder() . '/reserved/1', ''); // stray file where a job dir belongs

        $other = Job::create(function(){ return 456; });
        $adapter->push($other); // lands at index 2

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved, 'A blocked index must not stop the queue from serving later jobs.');
        $this->assertEquals($other->getJobId(), $reserved->getJobId());

        $adapter->delete($reserved);
        unlink($adapter->getFolder() . '/reserved/1');
        $adapter->clear();
    }

    public function testClaimTaskRunSucceedsOnFirstClaim()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $taskId  = 'claim-test-task-1';

        $this->assertTrue($adapter->claimTaskRun($taskId, '100'));

        unlink(__DIR__ . '/../tmp/pop-queue/claim-task-' . $taskId);
    }

    public function testClaimTaskRunRejectsSameWindowWhileLive()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $taskId  = 'claim-test-task-2';

        $this->assertTrue($adapter->claimTaskRun($taskId, '100'));
        $this->assertFalse($adapter->claimTaskRun($taskId, '100'));

        unlink(__DIR__ . '/../tmp/pop-queue/claim-task-' . $taskId);
    }

    public function testClaimTaskRunSucceedsForADifferentWindowWhilePreviousIsStillLive()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $taskId  = 'claim-test-task-3';

        $this->assertTrue($adapter->claimTaskRun($taskId, '100'));
        $this->assertTrue($adapter->claimTaskRun($taskId, '101'));

        unlink(__DIR__ . '/../tmp/pop-queue/claim-task-' . $taskId);
    }

    public function testClaimTaskRunSucceedsForSameWindowAfterExpiry()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $taskId  = 'claim-test-task-4';
        $path    = __DIR__ . '/../tmp/pop-queue/claim-task-' . $taskId;

        // Write an already-expired claim marker directly, rather than a
        // real 30-second sleep.
        file_put_contents($path, '100:' . (time() - 1));

        $this->assertTrue($adapter->claimTaskRun($taskId, '100'));

        unlink($path);
    }

    public function testClaimTaskMarkerFilenameDoesNotCorruptGetTasks()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $task    = Task::create(function(){ echo 'Task #1'; })->everyMinute();
        $adapter->schedule($task);

        $adapter->claimTaskRun($task->getJobId(), '100');

        $this->assertCount(1, $adapter->getTasks());
        $this->assertEquals($task->getJobId(), $adapter->getTasks()[0]);

        $adapter->clearTasks();
    }

    public function testRemoveTaskClearsClaimState()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $task    = Task::create(function(){ echo 'Task #1'; })->everyMinute();
        $adapter->schedule($task);

        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));
        $adapter->removeTask($task->getJobId());

        $this->assertFileDoesNotExist(__DIR__ . '/../tmp/pop-queue/claim-task-' . $task->getJobId());

        $adapter->schedule($task);
        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));

        $adapter->clearTasks();
    }

    public function testClearTasksSweepsOrphanedClaimMarkers()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue', 'FILO');
        $orphanPath = __DIR__ . '/../tmp/pop-queue/claim-task-orphaned-id-with-no-task-file';
        file_put_contents($orphanPath, '100:' . (time() + 30));

        $adapter->clearTasks();

        $this->assertFileDoesNotExist($orphanPath);
    }

    /**
     * A payload that exists but cannot be read is what a worker sees when a
     * peer claims and unlinks it between the file_exists() check and the read.
     * file_get_contents() reports that with false, and under
     * declare(strict_types=1) false reaching PayloadSigner::verify(string) is a
     * TypeError - so the adapter has to reject it before it gets that far.
     *
     * Mode 0000 is the portable way to force that false; note that a directory
     * does NOT work, since file_get_contents() on one returns '' on Linux.
     */
    public function testGetTaskReturnsNullWhenThePayloadCannotBeRead()
    {
        $folder = __DIR__ . '/../tmp/pop-queue';
        $path   = $folder . '/task-unreadable';

        file_put_contents($path, 'irrelevant');
        chmod($path, 0000);

        if (is_readable($path)) {
            @chmod($path, 0644);
            @unlink($path);
            $this->markTestSkipped('Running as a user that can read mode-0000 files.');
        }

        try {
            $this->assertNull(File::create($folder)->getTask('unreadable'));
        } finally {
            @chmod($path, 0644);
            @unlink($path);
        }
    }

    public function testReserveSkipsAJobWhosePayloadCannotBeRead()
    {
        $folder  = __DIR__ . '/../tmp/pop-queue';
        $adapter = File::create($folder);
        $adapter->clear();

        $pendingDir  = $folder . '/pending/1';
        $payloadFile = $pendingDir . '/payload';
        @mkdir($pendingDir, 0777, true);
        file_put_contents($payloadFile, 'irrelevant');
        chmod($payloadFile, 0000);

        if (is_readable($payloadFile)) {
            @chmod($payloadFile, 0644);
            $adapter->clear();
            $this->markTestSkipped('Running as a user that can read mode-0000 files.');
        }

        try {
            $this->assertNull($adapter->reserve());
        } finally {
            @chmod($payloadFile, 0644);
            $adapter->clear();
        }
    }

    /**
     * Separate from the unreadable case above: here the payload reads fine but
     * doesn't unserialize. unserialize() answers false, and false returned from
     * a ": ?Task" method is a TypeError in any mode - a corrupt task file has
     * to read as "no such task" rather than crash the caller.
     */
    public function testGetTaskReturnsNullForACorruptPayload()
    {
        $folder = __DIR__ . '/../tmp/pop-queue';
        $path   = $folder . '/task-corrupt';

        file_put_contents($path, 'this is not a serialized task');

        try {
            $this->assertNull(File::create($folder)->getTask('corrupt'));
        } finally {
            @unlink($path);
        }
    }

    /**
     * getEndIndex() is cached per instance now, so push() derives its index from
     * memory rather than re-scanning both directories every time. The cache is
     * only ever a hint, and the property that has to survive it is the one
     * everything else depends on: indices stay unique and strictly increasing,
     * and FIFO order still comes out the way it went in.
     */
    public function testPushKeepsIndicesUniqueAndOrderedWithTheIndexCached()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        for ($i = 1; $i <= 12; $i++) {
            $adapter->push(Job::create(function() use ($i) { return $i; }));
        }

        $indices = array_map('intval', $adapter->getFolders($adapter->getFolder() . '/pending'));
        sort($indices, SORT_NUMERIC);

        $this->assertCount(12, $indices);
        $this->assertEquals($indices, array_unique($indices));
        $this->assertEquals(range(1, 12), $indices);

        $adapter->clear();
    }

    /**
     * A cleared queue restarts its numbering from 1. Without resetting the
     * cached high-water mark, clear() would leave push() counting on from
     * wherever the old queue stopped.
     */
    public function testClearResetsTheCachedEndIndex()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $adapter->push(Job::create(function() { return 1; }));
        $adapter->push(Job::create(function() { return 2; }));
        $this->assertEquals([1, 2], $this->pendingIndices($adapter));

        $adapter->clear();
        $adapter->push(Job::create(function() { return 3; }));
        $this->assertEquals([1], $this->pendingIndices($adapter));

        $adapter->clear();
    }

    /**
     * An index is taken if a directory bearing it exists in *either* pending/ or
     * reserved/, but mkdir() only ever knows about pending/. With the end index
     * cached, a stale hint can aim push() straight at an index that is currently
     * sitting in reserved/ - so push() has to notice and move past it, or the
     * two directories end up holding different jobs under the same index.
     */
    public function testPushSkipsAnIndexAlreadyHeldInReserved()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        // Put a job in reserved/ under index 1, the index a fresh queue's next
        // push would otherwise take.
        $adapter->push(Job::create(function() { return 1; }));
        $this->assertNotNull($adapter->reserve());
        $this->assertEquals([1], array_map('intval', $adapter->getFolders($adapter->getFolder() . '/reserved')));

        // Force the hint back to "empty queue" the way a stale cache would read.
        $reset = new \ReflectionProperty(File::class, 'endIndex');
        $reset->setValue($adapter, 0);

        $adapter->push(Job::create(function() { return 2; }));

        $this->assertNotContains(1, $this->pendingIndices($adapter));
        $this->assertCount(1, $this->pendingIndices($adapter));

        $adapter->clear();
    }

    /**
     * reserve() writes a 'job-id' sidecar so release()/delete()/bury() can find
     * a reserved job without unserializing every reserved payload to read one
     * string off each.
     */
    public function testReserveWritesAJobIdSidecarAndDeleteRemovesTheWholeDirectory()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $job = Job::create(function() { return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);

        $dir = $adapter->getFolder() . '/reserved/1';
        $this->assertFileExists($dir . '/job-id');
        $this->assertEquals($reserved->getJobId(), file_get_contents($dir . '/job-id'));

        // rmdir() fails on a non-empty directory, so the sidecar has to be
        // cleaned up along with the payload and lease or delete() silently
        // leaves the job behind.
        $adapter->delete($reserved);
        $this->assertDirectoryDoesNotExist($dir);
        $this->assertEquals(0, $adapter->count());

        $adapter->clear();
    }

    /**
     * The sidecar is an optimization, never a source of truth: a directory
     * written before it existed - or by a reserve() that died between its
     * rename() and its sidecar write - still has to be findable by falling back
     * to the payload.
     */
    public function testReleaseStillFindsAReservedJobWithNoSidecar()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $job = Job::create(function() { return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);

        unlink($adapter->getFolder() . '/reserved/1/job-id');

        $adapter->release($reserved, 0);

        $this->assertEquals([1], $this->pendingIndices($adapter));
        $this->assertEmpty($adapter->getFolders($adapter->getFolder() . '/reserved'));

        $adapter->clear();
    }

    /**
     * getFolders()/getFiles() moved off scandir() onto FilesystemIterator, which
     * neither sorts nor returns the dot entries. The contract they have to keep
     * is what they exclude: dots, '.empty', and entries of the wrong kind.
     */
    public function testDirectoryListingSeparatesFilesFromFoldersAndSkipsPlaceholders()
    {
        $adapter = File::create(__DIR__ . '/../tmp/pop-queue');
        $adapter->clear();

        $folder = $adapter->getFolder();
        touch($folder . '/.empty');
        file_put_contents($folder . '/listing-probe', 'x');

        try {
            $files   = $adapter->getFiles($folder);
            $folders = $adapter->getFolders($folder);

            $this->assertContains('listing-probe', $files);
            $this->assertNotContains('.empty', $files);
            $this->assertNotContains('.', $files);
            $this->assertNotContains('..', $files);

            $this->assertContains('pending', $folders);
            $this->assertContains('reserved', $folders);
            $this->assertNotContains('listing-probe', $folders);

            // A missing directory reads as empty rather than raising.
            $this->assertSame([], $adapter->getFiles($folder . '/does-not-exist'));
            $this->assertSame([], $adapter->getFolders($folder . '/does-not-exist'));
        } finally {
            @unlink($folder . '/listing-probe');
        }
    }

    /**
     * Pending job indices, numerically sorted - the listing is unordered now
     * that it no longer goes through scandir().
     */
    protected function pendingIndices(File $adapter): array
    {
        $indices = array_map('intval', $adapter->getFolders($adapter->getFolder() . '/pending'));
        sort($indices, SORT_NUMERIC);

        return $indices;
    }

}
