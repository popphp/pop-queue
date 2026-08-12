<?php

namespace Pop\Queue\Test\Registry;

use Pop\Queue\Registry\WorkerRecord;
use PHPUnit\Framework\TestCase;

class WorkerRecordTest extends TestCase
{

    public function testCreateGeneratesIdentity()
    {
        $record = WorkerRecord::create('billing-worker', ['billing'], WorkerRecord::MODE_DAEMON);

        $this->assertNotEmpty($record->getId());
        $this->assertEquals('billing-worker', $record->getName());
        $this->assertEquals(['billing'], $record->getQueues());
        $this->assertEquals('daemon', $record->getMode());
        $this->assertEquals(gethostname(), $record->getHost());
        $this->assertEquals(getmypid(), $record->getPid());
        $this->assertNull($record->getCurrentJobId());
        $this->assertEquals(0, $record->getJobsProcessed());
        $this->assertEquals(0, $record->getJobsFailed());
    }

    public function testCreateGeneratesUniqueIdsWithinTheSameProcess()
    {
        $this->assertNotEquals(WorkerRecord::create()->getId(), WorkerRecord::create()->getId());
    }

    public function testDefaultModeIsSinglePass()
    {
        $this->assertEquals('single-pass', WorkerRecord::create()->getMode());
    }

    public function testIsStaleBoundary()
    {
        $now = time();

        // last seen 10s ago, threshold 90 - fresh
        $fresh = new WorkerRecord('id', 'host', 123, $now - 100, $now - 10);
        $this->assertFalse($fresh->isStale(90));

        // last seen 200s ago, threshold 90 - stale
        $stale = new WorkerRecord('id', 'host', 123, $now - 300, $now - 200);
        $this->assertTrue($stale->isStale(90));

        // exactly at the threshold is NOT stale (strictly greater than)
        $edge = new WorkerRecord('id', 'host', 123, $now - 300, $now - 90);
        $this->assertFalse($edge->isStale(90));
    }

    public function testTouchUpdatesLastSeen()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 300, $now - 200);
        $this->assertTrue($record->isStale(90));

        $record->touch();
        $this->assertFalse($record->isStale(90));
    }

    public function testCurrentJobDurationIsNullWhenIdle()
    {
        $this->assertNull(WorkerRecord::create()->getCurrentJobDuration());
    }

    public function testSetAndClearCurrentJob()
    {
        $record = WorkerRecord::create();
        $record->setCurrentJob('job-abc', 'billing', 30);

        $this->assertEquals('job-abc', $record->getCurrentJobId());
        $this->assertEquals('billing', $record->getCurrentQueue());
        $this->assertEquals(30, $record->getCurrentJobTimeout());
        $this->assertIsInt($record->getCurrentJobDuration());

        $record->clearCurrentJob();
        $this->assertNull($record->getCurrentJobId());
        $this->assertNull($record->getCurrentQueue());
        $this->assertNull($record->getCurrentJobTimeout());
        $this->assertNull($record->getCurrentJobDuration());
    }

    public function testCounters()
    {
        $record = WorkerRecord::create();
        $record->incrementProcessed();
        $record->incrementProcessed();
        $record->incrementFailed();

        $this->assertEquals(2, $record->getJobsProcessed());
        $this->assertEquals(1, $record->getJobsFailed());
    }

    public function testIsLikelyStuckFalseWhenFresh()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 100, $now - 5);
        $record->setCurrentJob('job-abc', 'billing', 30);

        $this->assertFalse($record->isLikelyStuck(90));
    }

    public function testIsLikelyStuckFalseWhenStaleButIdle()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 300, $now - 200);

        // Stale with no current job is "wedged idle" - real, but not stuck ON anything,
        // so isLikelyStuck() is false and getStaleWorkers() is what surfaces it.
        $this->assertTrue($record->isStale(90));
        $this->assertFalse($record->isLikelyStuck(90));
    }

    public function testIsLikelyStuckTrueWhenStaleAndJobPastItsTimeout()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 600, $now - 200);
        $record->setCurrentJob('job-abc', 'billing', 30);
        // job started 200s ago against a 30s timeout
        $record->setCurrentJobStartedAt($now - 200);

        $this->assertTrue($record->isLikelyStuck(90));
    }

    public function testIsLikelyStuckFalseWhenStaleButJobStillWithinItsTimeout()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 600, $now - 200);
        $record->setCurrentJob('job-abc', 'billing', 3600);
        $record->setCurrentJobStartedAt($now - 200);

        // A legitimately long job with a generous timeout is not stuck.
        $this->assertFalse($record->isLikelyStuck(90));
    }

    public function testIsLikelyStuckFallsBackToStaleThresholdWhenJobHasNoTimeout()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 600, $now - 200);
        $record->setCurrentJob('job-abc', 'billing', null);
        $record->setCurrentJobStartedAt($now - 200);

        // No timeout on the job, so the stale threshold is the fallback yardstick.
        $this->assertTrue($record->isLikelyStuck(90));
    }

    public function testIsLikelyStuckIsFalseExactlyAtTheJobTimeoutBoundary()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 600, $now - 200);
        $record->setCurrentJob('job-abc', 'billing', 200);
        $record->setCurrentJobStartedAt($now - 200);

        // duration == timeout is NOT past it (strict >), matching isStale()
        $this->assertFalse($record->isLikelyStuck(90));
    }

    public function testIsLikelyStuckIsFalseExactlyAtTheStaleThresholdWhenJobHasNoTimeout()
    {
        $now    = time();
        $record = new WorkerRecord('id', 'host', 123, $now - 600, $now - 200);
        $record->setCurrentJob('job-abc', 'billing', null);
        $record->setCurrentJobStartedAt($now - 90);

        // duration == staleSeconds is NOT past it
        $this->assertFalse($record->isLikelyStuck(90));
    }

    public function testToArrayFromArrayRoundTrip()
    {
        $record = WorkerRecord::create('billing-worker', ['billing', 'email'], WorkerRecord::MODE_DAEMON);
        $record->setCurrentJob('job-abc', 'billing', 30);
        $record->incrementProcessed();
        $record->incrementFailed();

        $restored = WorkerRecord::fromArray($record->toArray());

        $this->assertEquals($record->getId(), $restored->getId());
        $this->assertEquals($record->getName(), $restored->getName());
        $this->assertEquals($record->getHost(), $restored->getHost());
        $this->assertEquals($record->getPid(), $restored->getPid());
        $this->assertEquals($record->getStartedAt(), $restored->getStartedAt());
        $this->assertEquals($record->getLastSeenAt(), $restored->getLastSeenAt());
        $this->assertEquals($record->getQueues(), $restored->getQueues());
        $this->assertEquals($record->getMode(), $restored->getMode());
        $this->assertEquals($record->getCurrentJobId(), $restored->getCurrentJobId());
        $this->assertEquals($record->getCurrentQueue(), $restored->getCurrentQueue());
        $this->assertEquals($record->getCurrentJobStartedAt(), $restored->getCurrentJobStartedAt());
        $this->assertEquals($record->getCurrentJobTimeout(), $restored->getCurrentJobTimeout());
        $this->assertEquals($record->getJobsProcessed(), $restored->getJobsProcessed());
        $this->assertEquals($record->getJobsFailed(), $restored->getJobsFailed());
    }

    public function testJsonRoundTripSurvivesEncoding()
    {
        $record   = WorkerRecord::create('billing-worker', ['billing'], WorkerRecord::MODE_DAEMON);
        $restored = WorkerRecord::fromArray(json_decode(json_encode($record->toArray()), true));

        $this->assertEquals($record->getId(), $restored->getId());
        $this->assertEquals($record->getQueues(), $restored->getQueues());
        $this->assertEquals($record->getMode(), $restored->getMode());
    }

}
