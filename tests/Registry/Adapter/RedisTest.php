<?php

namespace Pop\Queue\Test\Registry\Adapter;

use Pop\Queue\Registry\Adapter\Redis;
use Pop\Queue\Registry\WorkerRecord;
use PHPUnit\Framework\TestCase;

class RedisTest extends TestCase
{

    protected function registry(): Redis
    {
        $registry = new Redis();
        $registry->prune(-1); // clear between tests
        return $registry;
    }

    public function testConstructor()
    {
        $registry = new Redis();
        $this->assertInstanceOf('Redis', $registry->getRedis());
        $this->assertEquals('pop-registry', $registry->getPrefix());
    }

    public function testWriteAndRead()
    {
        $registry = $this->registry();
        $record   = WorkerRecord::create('worker-a', ['billing'], WorkerRecord::MODE_DAEMON);

        $registry->write($record);
        $read = $registry->read($record->getId());

        $this->assertInstanceOf('Pop\Queue\Registry\WorkerRecord', $read);
        $this->assertEquals($record->getId(), $read->getId());
        $this->assertEquals('worker-a', $read->getName());
        $this->assertEquals(['billing'], $read->getQueues());
        $this->assertEquals('daemon', $read->getMode());
    }

    public function testReadReturnsNullForUnknownId()
    {
        $this->assertNull($this->registry()->read('does-not-exist'));
    }

    public function testWriteIsAnUpsert()
    {
        $registry = $this->registry();
        $record   = WorkerRecord::create('worker-a');

        $registry->write($record);
        $record->incrementProcessed();
        $registry->write($record);

        $this->assertCount(1, $registry->all());
        $this->assertEquals(1, $registry->read($record->getId())->getJobsProcessed());
    }

    public function testAllReturnsEveryRecord()
    {
        $registry = $this->registry();
        $registry->write(WorkerRecord::create('worker-a'));
        $registry->write(WorkerRecord::create('worker-b'));

        $this->assertCount(2, $registry->all());
    }

    public function testDelete()
    {
        $registry = $this->registry();
        $record   = WorkerRecord::create('worker-a');
        $registry->write($record);

        $registry->delete($record->getId());

        $this->assertNull($registry->read($record->getId()));
        $this->assertEmpty($registry->all());
    }

    public function testPruneRemovesOnlyRecordsOlderThanTheThreshold()
    {
        $registry = $this->registry();
        $now      = time();

        $registry->write(new WorkerRecord('fresh-aaa', 'host', 1, $now - 10,   $now - 10));
        $registry->write(new WorkerRecord('old-bbb',   'host', 2, $now - 5000, $now - 5000));

        $removed = $registry->prune(3600);

        $this->assertEquals(1, $removed);
        $this->assertNull($registry->read('old-bbb'));
        $this->assertNotNull($registry->read('fresh-aaa'));
    }

    public function testAllSkipsAnUndecodableEntry()
    {
        $registry = $this->registry();
        $registry->getRedis()->set('pop-registry:worker:junk', 'not valid json');
        $good = WorkerRecord::create('worker-a');
        $registry->write($good);

        $all = $registry->all();

        $this->assertCount(1, $all);
        $this->assertArrayHasKey($good->getId(), $all);
    }

    public function testPruneReapsUndecodableRecords()
    {
        $registry = $this->registry();
        $registry->getRedis()->set('pop-registry:worker:junk', 'not valid json');
        $registry->write(WorkerRecord::create('worker-a'));

        // The good record is fresh, so only the undecodable one is reaped.
        $removed = $registry->prune(3600);

        $this->assertEquals(1, $removed);
        $this->assertCount(1, $registry->all());
    }

    public function testWriteStoresASnapshotNotAReferenceToTheCallersObject()
    {
        $registry = $this->registry();
        $record   = WorkerRecord::create('worker-a');
        $registry->write($record);

        // Mutate the caller's object AFTER writing, with no second write().
        $record->incrementProcessed();
        $record->setCurrentJob('job-abc', 'billing', 30);

        $stored = $registry->read($record->getId());
        $this->assertEquals(0, $stored->getJobsProcessed());
        $this->assertNull($stored->getCurrentJobId());

        $all = $registry->all();
        $this->assertEquals(0, $all[$record->getId()]->getJobsProcessed());
        $this->assertNull($all[$record->getId()]->getCurrentJobId());
    }

}
