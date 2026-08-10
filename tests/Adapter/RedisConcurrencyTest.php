<?php

namespace Pop\Queue\Test\Adapter;

use Pop\Queue\Adapter\Redis;
use Pop\Queue\Process\Job;

class RedisConcurrencyTest extends ConcurrencyTestCase
{

    public function testConcurrentReserveNeverDoubleClaims()
    {
        $prefix  = 'pop-queue-concurrency';
        $adapter = new Redis('localhost', 6379, $prefix);
        $adapter->clear();

        $workerCount = 8;
        $jobs = [];
        for ($i = 0; $i < $workerCount; $i++) {
            $job = Job::create(function(){ return 1; });
            $jobs[] = $job;
            $adapter->push($job);
        }

        $results = $this->runConcurrentReservations('redis', 'localhost:6379:' . $prefix, $workerCount);

        $claimed = array_filter($results, fn($r) => $r !== 'NULL');
        $this->assertCount($workerCount, $claimed, 'Every pushed job should have been claimed by exactly one worker.');
        $this->assertCount($workerCount, array_unique($claimed), 'No job should have been claimed by more than one worker.');

        foreach ($jobs as $job) {
            $this->assertContains($job->getJobId(), $claimed);
        }

        $adapter->clear();
    }

    /**
     * ABA regression test: reproduces the exact race described in the task
     * brief deterministically, without relying on real process timing.
     *
     * Worker A reads the reserved sorted set and sees an entry that looks
     * expired (a stale snapshot). Before A acts on that read, Worker B
     * reclaims that same expired entry and immediately re-claims it,
     * writing the identical serialized value back into the reserved set
     * with a brand-new, non-expired score (a reclaim never re-serializes
     * the job, so the bytes are indistinguishable before/after). A plain
     * zRem($key, $value) would remove B's fresh claim because it matches
     * by value only, ignoring the current score - a genuine double-claim.
     * The Lua-based atomic compare-and-remove must instead re-verify the
     * *current* score at the moment it acts and refuse to remove an entry
     * that is no longer expired, even when handed the stale $value/$now
     * pair a naive implementation would have blindly trusted.
     */
    public function testReclaimSurvivesFreshlyReclaimedEntryWithStaleRead()
    {
        $prefix  = 'pop-queue-aba';
        $adapter = new Redis('localhost', 6379, $prefix, null, 1); // 1-second lease
        $adapter->clear();

        $job = Job::create(function(){ return 1; });
        $adapter->push($job);

        $reserved = $adapter->reserve();
        $this->assertNotNull($reserved);

        $reservedKey = $prefix . ':reserved';
        $redis       = $adapter->getRedis();

        // Let the lease genuinely expire.
        sleep(2);

        // Worker A's stale read: this is exactly what reclaimExpiredLeases()
        // does internally - scan for candidates whose score looks <= now.
        $now       = time();
        $candidates = $redis->zRangeByScore($reservedKey, '-inf', (string)$now);
        $this->assertCount(1, $candidates, 'Precondition: the lease must look expired at read time.');
        $value = $candidates[0];

        // Interleave: Worker B reclaims-and-immediately-re-claims the same
        // entry before Worker A acts on its stale read. Net effect on the
        // reserved set is identical to B's real reclaim+lRem+zAdd cycle:
        // the same serialized value, now scored with a fresh, non-expired
        // lease far in the future.
        $freshScore = time() + 60;
        $redis->zAdd($reservedKey, $freshScore, $value);

        // Worker A now acts on its stale $value/$now pair via the adapter's
        // real atomic reclaim primitive (not a hand-rolled reimplementation).
        $atomicReclaim = new \ReflectionMethod($adapter, 'atomicReclaimIfStillExpired');
        $atomicReclaim->setAccessible(true);
        $result = $atomicReclaim->invoke($adapter, $value, $now);

        $this->assertSame(0, $result, 'A stale-read-driven reclaim must not remove a freshly re-claimed entry.');

        // Worker B's fresh claim must have survived untouched, with its
        // fresh score intact - not evicted back into pending.
        $survivingScore = $redis->zScore($reservedKey, $value);
        $this->assertEquals($freshScore, $survivingScore, "Worker B's freshly re-claimed entry must survive with its fresh score.");

        $pending = $redis->lRange($prefix, 0, -1);
        $this->assertNotContains($value, $pending, 'The freshly re-claimed job must not have been pushed back to pending.');

        // A subsequent reserve() must see nothing available - the only job
        // is legitimately held (by "Worker B") under a fresh, unexpired lease.
        $this->assertNull($adapter->reserve());

        $adapter->clear();
    }

}
