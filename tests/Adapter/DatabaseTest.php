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

        $adapter = new Database($db, 'pop_queue', null, 1); // 1-second lease
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
