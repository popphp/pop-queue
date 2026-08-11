<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Db\Db as PopDb;
use Pop\Queue\Adapter\Database;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\PayloadSigner;
use Pop\Queue\Process\Task;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{

    protected function tearDown(): void
    {
        PayloadSigner::setKey(null);
    }

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
    }

    public function testPushAndReserve()
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
        $this->assertEquals(1, $adapter->count());

        $reserved = $adapter->reserve();
        $this->assertEquals(123, $reserved->run());
        $adapter->delete($reserved);
    }

    public function testReserveReturnsNullWhenEmpty()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->clear();

        $this->assertNull($adapter->reserve());
    }

    public function testRelease()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Database($db);
        $adapter->push($job);
        $reserved = $adapter->reserve();

        $adapter->release($reserved);
        $this->assertEquals(1, $adapter->count());
        $this->assertNotNull($adapter->reserve());
        $adapter->clear();
    }

    public function testBury()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Database($db);
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
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){
            return 123;
        });

        $adapter = new Database($db);
        $adapter->push($job);
        $adapter->bury($adapter->reserve(), 'reason');
        $adapter->retryDeadJob($job->getJobId());

        $this->assertFalse($adapter->hasDeadJobs());
        $this->assertTrue($adapter->hasJobs());
        $adapter->clear();
    }

    public function testDeleteDeadJobDoesNotTouchTasks()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job  = Job::create(function(){ return 123; });
        $task = Task::create(function(){ echo 'Task #1'; })->everyMinute();

        $adapter = new Database($db);
        $adapter->clear();
        $adapter->clearTasks();
        $adapter->clearDead();

        $adapter->push($job);
        $adapter->schedule($task);

        // A task ID is not a dead job ID; deleting it as one must be a no-op
        $adapter->deleteDeadJob($task->getJobId());
        $this->assertTrue($adapter->hasTasks());

        // Nor may it reach a live pending job
        $adapter->deleteDeadJob($job->getJobId());
        $this->assertEquals(1, $adapter->count());

        $adapter->clear();
        $adapter->clearTasks();
    }

    public function testRemoveTaskDoesNotTouchJobsOrDeadJobs()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $pending = Job::create(function(){ return 1; });
        $dead    = Job::create(function(){ return 2; });

        $adapter = new Database($db);
        $adapter->clear();
        $adapter->clearTasks();
        $adapter->clearDead();

        $adapter->push($dead);
        $adapter->bury($adapter->reserve(), 'reason');
        $adapter->push($pending);

        // A job ID is not a task ID; removing it as one must be a no-op
        $adapter->removeTask($pending->getJobId());
        $adapter->removeTask($dead->getJobId());

        $this->assertEquals(1, $adapter->count());
        $this->assertEquals(1, $adapter->countDead());

        // And a task ID must not reach a job row
        $this->assertNull($adapter->getTask($pending->getJobId()));

        $adapter->clear();
        $adapter->clearDead();
    }

    public function testGetDeadJobDoesNotReturnLiveJobsOrTasks()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job  = Job::create(function(){ return 123; });
        $task = Task::create(function(){ echo 'Task #1'; })->everyMinute();

        $adapter = new Database($db);
        $adapter->clear();
        $adapter->clearTasks();
        $adapter->clearDead();

        $adapter->push($job);
        $adapter->schedule($task);

        $this->assertNull($adapter->getDeadJob($job->getJobId()));
        $this->assertNull($adapter->getDeadJob($task->getJobId()));

        $adapter->clear();
        $adapter->clearTasks();
    }

    public function testReserveMovesPastAlreadyReservedJobs()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });

        $adapter = new Database($db);
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
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){ return 123; });
        $job->delay(60);

        $adapter = new Database($db);
        $adapter->clear();
        $adapter->push($job);

        $this->assertTrue($adapter->hasJobs());
        $this->assertNull($adapter->reserve());
        $adapter->clear();
    }

    public function testReserveSkipsDelayedJobAndClaimsAvailableOne()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $delayed   = Job::create(function(){ return 1; });
        $available = Job::create(function(){ return 2; });
        $delayed->delay(60);

        $adapter = new Database($db);
        $adapter->clear();
        $adapter->push($delayed);
        $adapter->push($available);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);
        $this->assertEquals($available->getJobId(), $reserved->getJobId());

        $adapter->clear();
    }

    public function testReserveFilo()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job1 = Job::create(function(){ return 1; });
        $job2 = Job::create(function(){ return 2; });

        $adapter = new Database($db);
        $adapter->clear();
        $adapter->setPriority('FILO');
        $adapter->push($job1);
        $adapter->push($job2);

        $this->assertEquals($job2->getJobId(), $adapter->reserve()->getJobId());
        $this->assertEquals($job1->getJobId(), $adapter->reserve()->getJobId());

        $adapter->clear();
    }

    public function testClearDoesNotTouchTasksOrDeadJobs()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job  = Job::create(function(){ return 123; });
        $task = Task::create(function(){ echo 'Task #1'; })->everyMinute();

        $adapter = new Database($db);
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

    public function testLeaseReclaim()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){ return 123; });

        // Deliberately a long lease, expired by hand rather than by sleeping:
        // an earlier version of this test used a 1-second lease and two
        // back-to-back reserve() calls, which legitimately (and correctly)
        // reclaimed the job on the second call whenever those two calls
        // straddled a one-second wall-clock boundary - real, intermittent CI
        // flakiness in the test, not in the adapter.
        $adapter = new Database($db, 'pop_queue', null, 60);
        $adapter->clear();
        $adapter->push($job);

        $first = $adapter->reserve();
        $this->assertNotNull($first);

        // The claim recorded a lease comfortably in the future, so it is not
        // reclaimable while it's still held.
        $sql = $db->createSql();
        $sql->select('reserved_until')->from('pop_queue')->where('job_id = :job_id');
        $db->prepare($sql);
        $db->bindParams(['job_id' => $job->getJobId()]);
        $db->execute();
        $rows = $db->fetchAll();
        $this->assertGreaterThan(time(), (int)$rows[0]['reserved_until']);
        $this->assertNull($adapter->reserve());

        // Age the claim past its lease deterministically.
        $sql = $db->createSql();
        $sql->update('pop_queue')->values(['reserved_until' => ':reserved_until'])->where('job_id = :job_id');
        $db->prepare($sql);
        $db->bindParams(['reserved_until' => (time() - 100), 'job_id' => $job->getJobId()]);
        $db->execute();

        $second = $adapter->reserve();
        $this->assertNotNull($second);
        $this->assertEquals($job->getJobId(), $second->getJobId());

        $adapter->delete($second);
    }

    public function testReleaseHonorsBackoff()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){ return 123; });
        $job->setBackoff(60);

        $adapter = new Database($db);
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $reserved->failed();
        $adapter->release($reserved);

        $this->assertNull($adapter->reserve());
        $adapter->clear();
    }

    public function testReleaseHonorsExplicitDelayOverridingBackoff()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){ return 123; });
        $job->setBackoff(60);

        $adapter = new Database($db);
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $adapter->release($reserved, 0);

        $this->assertNotNull($adapter->reserve());
        $adapter->clear();
    }

    public function testConstructorWithLeaseSeconds()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $adapter = new Database($db, 'pop_queue', null, 30);
        $this->assertInstanceOf('Pop\Queue\Adapter\Database', $adapter);
    }

    public function testCreateFactoryPassesThroughPriorityAndLease()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $adapter = Database::create($db, 'pop_queue', 'FILO', 30);
        $this->assertEquals('FILO', $adapter->getPriority());
    }

    public function testConcurrentClaimTokenProofRejectsLoser()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){ return 123; });

        $adapter = new Database($db);
        $adapter->clear();
        $adapter->push($job);

        // Positive control: the winner's real reserve() call actually claims
        // the job. This makes the negative assertion below meaningful - a
        // reserve() that was silently broken (e.g. always returning null)
        // could not accidentally satisfy it.
        $winner = $adapter->reserve();
        $this->assertNotNull($winner);
        $this->assertEquals($job->getJobId(), $winner->getJobId());

        // Look up the row id the winner actually claimed.
        $idSql = $db->createSql();
        $idSql->select('id')->from('pop_queue')->where('job_id = :job_id');
        $db->prepare($idSql);
        $db->bindParams(['job_id' => $job->getJobId()]);
        $db->execute();
        $rowId = (int)$db->fetchAll()[0]['id'];

        // Loser: simulate a second worker whose SELECT scan went stale - it
        // read the row while still pending, but by the time its UPDATE runs
        // the winner has already claimed it. Build the identical conditional
        // UPDATE reserve() issues, with its own claim token, via the
        // adapter's own buildEligibleWhere() helper (not a hand-rolled
        // reimplementation, so this exercises the real production predicate).
        $now        = time();
        $loserToken = 'loser-token';

        $sql    = $db->createSql();
        $update = $sql->update('pop_queue')->values([
            'status'         => ':status',
            'reserved_until' => ':reserved_until',
            'reserved_by'    => ':reserved_by'
        ]);

        $buildEligibleWhere = new \ReflectionMethod($adapter, 'buildEligibleWhere');
        $buildEligibleWhere->setAccessible(true);
        $update->where($buildEligibleWhere->invoke($adapter, $update, $now));
        $update->andWhere('job_id = :job_id');

        $db->prepare($sql);
        $db->bindParams([
            'status'         => 0,
            'reserved_until' => ($now + 60),
            'reserved_by'    => $loserToken,
            'job_id'         => $job->getJobId()
        ]);
        $db->execute();

        // The loser's UPDATE's WHERE re-validates the row's real current
        // state (status/reserved_until) at write time, so it must not have
        // touched the already-claimed row: reserved_by must still be
        // whatever the winner's reserve() wrote, never the loser's token.
        $claimedBy = new \ReflectionMethod($adapter, 'claimedBy');
        $claimedBy->setAccessible(true);
        $this->assertNotEquals($loserToken, $claimedBy->invoke($adapter, $rowId));

        $adapter->clear();
    }

    public function testReserveSkipsCorruptPayload()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $adapter = new Database($db);
        $adapter->clear();

        // Insert a row with a corrupt/truncated payload directly, bypassing
        // push(), at a lower index so it is the first candidate under FIFO
        // ordering. Same shape as a payload whose class no longer exists
        // after a deploy (which unserializes to a __PHP_Incomplete_Class,
        // not an AbstractJob).
        $sql = $db->createSql();
        $sql->insert('pop_queue')->values([
            'index'   => ':index',
            'type'    => ':type',
            'job_id'  => ':job_id',
            'payload' => ':payload',
            'status'  => ':status'
        ]);
        $db->prepare($sql);
        $db->bindParams([
            'index'   => 1,
            'type'    => 'job',
            'job_id'  => 'corrupt-job-id',
            'payload' => base64_encode('not-valid-serialized-data'),
            'status'  => 1
        ]);
        $db->execute();

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);
        $this->assertEquals($job->getJobId(), $reserved->getJobId());

        // The corrupt row must be skipped outright, never claimed and leased
        // (status left at 1, no reserved_by token) - a leased corrupt row
        // would be reclaimed on lease expiry and poison every worker that
        // reserves after it, forever.
        $check = $db->createSql();
        $check->select(['status', 'reserved_by'])->from('pop_queue')->where('job_id = :job_id');
        $db->prepare($check);
        $db->bindParams(['job_id' => 'corrupt-job-id']);
        $db->execute();
        $rows = $db->fetchAll();
        $this->assertEquals(1, (int)$rows[0]['status']);
        $this->assertNull($rows[0]['reserved_by']);

        $adapter->delete($reserved);
        $adapter->clear();
    }

    public function testEnsureReservedUntilColumnBackfillsPhase1ReservedRows()
    {
        // Build a Phase-1-shaped table by hand, in an isolated sqlite file:
        // no reserved_until/reserved_by columns, and one row already sitting
        // at status = 0 - i.e. a job reserved under the pre-lease contract,
        // before this migration existed.
        $file = __DIR__ . '/../tmp/test-migration.sqlite';
        if (file_exists($file)) {
            unlink($file);
        }
        touch($file);
        chmod($file, 0777);

        $db = PopDb::sqliteConnect(['database' => $file]);

        $schema = $db->createSchema();
        $schema->create('pop_queue')
            ->int('id', 16)->increment()
            ->int('index', 16)->nullable()
            ->varchar('type', 255)
            ->varchar('job_id', 255)
            ->text('payload')
            ->int('status', 1)->defaultIs(1)
            ->primary('id');
        $db->query($schema);

        $job = Job::create(function(){ return 123; });

        $insertSql = $db->createSql();
        $insertSql->insert('pop_queue')->values([
            'index'   => ':index',
            'type'    => ':type',
            'job_id'  => ':job_id',
            'payload' => ':payload',
            'status'  => ':status'
        ]);
        $db->prepare($insertSql);
        $db->bindParams([
            'index'   => 1,
            'type'    => 'job',
            'job_id'  => $job->getJobId(),
            'payload' => base64_encode(serialize($job)),
            'status'  => 0 // reserved under the pre-lease Phase 1 contract
        ]);
        $db->execute();

        // Constructing a Database adapter against this table runs the
        // reserved_until/reserved_by migration. Without the backfill, this
        // row's reserved_until would be NULL forever - "NULL <= now" matches
        // neither branch of reserve()'s eligibility check, so the row would
        // never be reclaimable again.
        $adapter = new Database($db);

        $reclaimed = $adapter->reserve();
        $this->assertNotNull($reclaimed);
        $this->assertEquals($job->getJobId(), $reclaimed->getJobId());

        unlink($file);
    }

    public function testSecondAdapterConstructionDoesNotWipeLiveLeases()
    {
        // Regression test: the migration's backfill (reserved_until = 0 for
        // status = 0 rows) must only ever run the first time the
        // reserved_until column is added to a table - never on a table that
        // already has it. If it re-ran on every construction, then every
        // worker process starting up (each one constructs its own Database
        // adapter against the same shared table) would zero out
        // reserved_until - and therefore the lease - on every currently
        // in-flight job table-wide, making them all instantly reclaimable
        // and executed twice. This is the exact double-execution failure
        // this whole task exists to prevent, so it must not be reachable
        // via ordinary adapter construction.
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $job = Job::create(function(){ return 123; });

        $first = new Database($db);
        $first->clear();
        $first->push($job);

        // Establish a live lease.
        $reserved = $first->reserve();
        $this->assertNotNull($reserved);

        // A second worker constructing its own adapter against the same,
        // already-migrated table must not disturb the first adapter's lease.
        $second = new Database($db);
        $this->assertNull($second->reserve());

        $first->delete($reserved);
    }

    public function testClaimTaskRunSucceedsOnFirstClaim()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->clearTasks();

        $task = Task::create(function(){ return 'Task #1'; })->everySecond();
        $adapter->schedule($task);

        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));

        $adapter->clearTasks();
    }

    public function testClaimTaskRunRejectsSameWindowWhileLive()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->clearTasks();

        $task = Task::create(function(){ return 'Task #1'; })->everySecond();
        $adapter->schedule($task);

        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));
        $this->assertFalse($adapter->claimTaskRun($task->getJobId(), '100'));

        $adapter->clearTasks();
    }

    public function testClaimTaskRunSucceedsForADifferentWindowWhilePreviousIsStillLive()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->clearTasks();

        $task = Task::create(function(){ return 'Task #1'; })->everySecond();
        $adapter->schedule($task);

        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));
        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '101'));

        $adapter->clearTasks();
    }

    public function testClaimTaskRunSucceedsForSameWindowAfterExpiry()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->clearTasks();

        $task = Task::create(function(){ return 'Task #1'; })->everySecond();
        $adapter->schedule($task);

        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));

        // Force the stored claim to look expired directly, rather than a
        // real 30-second sleep.
        $sql = $db->createSql();
        $sql->update('pop_queue')->values(['reserved_until' => ':reserved_until'])
            ->where("type = 'task'")->andWhere('job_id = :job_id');
        $db->prepare($sql);
        $db->bindParams(['reserved_until' => (time() - 1), 'job_id' => $task->getJobId()]);
        $db->execute();

        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));

        $adapter->clearTasks();
    }

    public function testRemoveTaskClearsClaimState()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->clearTasks();

        $task = Task::create(function(){ return 'Task #1'; })->everySecond();
        $adapter->schedule($task);

        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));
        $adapter->removeTask($task->getJobId());
        $adapter->schedule($task);

        // Claiming the *same* window again after removeTask() must succeed
        // - removeTask() deletes the whole row, including its claim state.
        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));

        $adapter->clearTasks();
    }

    public function testConcurrentTaskClaimTokenProofRejectsLoser()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);
        $adapter->clearTasks();

        $task = Task::create(function(){ return 'Task #1'; })->everySecond();
        $adapter->schedule($task);

        // Positive control: the winner's real claimTaskRun() call actually
        // claims the window. This makes the negative assertion below
        // meaningful.
        $this->assertTrue($adapter->claimTaskRun($task->getJobId(), '100'));

        // Loser: simulate a second worker racing for the *same* window,
        // built via the adapter's own eligibility-predicate helper (not a
        // hand-rolled reimplementation), so this exercises the real
        // production predicate.
        $now        = time();
        $loserToken = '100:loser-token';

        $sql    = $db->createSql();
        $update = $sql->update('pop_queue')->values([
            'reserved_until' => ':reserved_until',
            'reserved_by'    => ':reserved_by'
        ]);

        $buildWhere = new \ReflectionMethod($adapter, 'buildTaskClaimEligibleWhere');
        $buildWhere->setAccessible(true);
        $update->where($buildWhere->invoke($adapter, $update, $task->getJobId(), '100', $now));

        $db->prepare($sql);
        $db->bindParams([
            'reserved_until' => ($now + 30),
            'reserved_by'    => $loserToken
        ]);
        $db->execute();

        // The loser's UPDATE's WHERE re-validates the row's real current
        // state at write time, so it must not have touched the
        // already-claimed row.
        $claimedBy = new \ReflectionMethod($adapter, 'claimedByTaskId');
        $claimedBy->setAccessible(true);
        $this->assertNotEquals($loserToken, $claimedBy->invoke($adapter, $task->getJobId()));

        $adapter->clearTasks();
    }

    public function testPushAndReserveWithSigningKeyConfigured()
    {
        PayloadSigner::setKey('test-secret-key');

        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $adapter = new Database($db);
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
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

        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);

        $adapter = new Database($db);
        $adapter->clear();

        $job = Job::create(function(){ return 123; });
        $adapter->push($job);

        // Directly corrupt the stored (signed) payload column, bypassing
        // the adapter entirely - simulates an attacker (or bit rot)
        // tampering with storage the adapter doesn't otherwise trust.
        $select = $db->createSql();
        $select->select(['id', 'payload'])->from('pop_queue')->where('job_id = :job_id');
        $db->prepare($select);
        $db->bindParams(['job_id' => $job->getJobId()]);
        $db->execute();
        $row = $db->fetchAll()[0];

        // Corrupt a character near the middle of the payload to ensure it's not padding
        $midpoint = (int)(strlen($row['payload']) / 2);
        $midChar  = substr($row['payload'], $midpoint, 1);
        $flipped  = chr((ord($midChar) + 1) % 256);
        $tampered = substr($row['payload'], 0, $midpoint) . $flipped . substr($row['payload'], $midpoint + 1);

        $update = $db->createSql();
        $update->update('pop_queue')->values(['payload' => ':payload'])->where('id = ' . (int)$row['id']);
        $db->prepare($update);
        $db->bindParams(['payload' => $tampered]);
        $db->execute();

        $reserved = $adapter->reserve();
        $this->assertNull($reserved);

        $adapter->clear();
    }

    public function testClear()
    {
        $db = PopDb::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Database($db);

        $adapter->clear();
        $adapter->clearDead();

        $this->assertFalse($adapter->hasJobs());
        $this->assertFalse($adapter->hasDeadJobs());

        unlink(__DIR__ . '/../tmp/test.sqlite');
    }

}
