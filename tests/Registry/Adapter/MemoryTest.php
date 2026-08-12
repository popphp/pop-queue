<?php

namespace Pop\Queue\Test\Registry\Adapter;

use Pop\Queue\Registry\Adapter\Memory;
use Pop\Queue\Registry\WorkerRecord;
use PHPUnit\Framework\TestCase;

class MemoryTest extends TestCase
{

    public function testWriteAndRead()
    {
        $registry = new Memory();
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
        $this->assertNull((new Memory())->read('does-not-exist'));
    }

    public function testWriteIsAnUpsert()
    {
        $registry = new Memory();
        $record   = WorkerRecord::create('worker-a');

        $registry->write($record);
        $record->incrementProcessed();
        $registry->write($record);

        $this->assertCount(1, $registry->all());
        $this->assertEquals(1, $registry->read($record->getId())->getJobsProcessed());
    }

    public function testAllReturnsEveryRecord()
    {
        $registry = new Memory();
        $registry->write(WorkerRecord::create('worker-a'));
        $registry->write(WorkerRecord::create('worker-b'));
        $registry->write(WorkerRecord::create('worker-c'));

        $all = $registry->all();
        $this->assertCount(3, $all);
        foreach ($all as $record) {
            $this->assertInstanceOf('Pop\Queue\Registry\WorkerRecord', $record);
        }
    }

    public function testAllIsEmptyInitially()
    {
        $this->assertEmpty((new Memory())->all());
    }

    public function testDelete()
    {
        $registry = new Memory();
        $record   = WorkerRecord::create('worker-a');
        $registry->write($record);

        $registry->delete($record->getId());

        $this->assertNull($registry->read($record->getId()));
        $this->assertEmpty($registry->all());
    }

    public function testDeleteUnknownIdIsANoOp()
    {
        $registry = new Memory();
        $registry->write(WorkerRecord::create('worker-a'));

        $registry->delete('does-not-exist');

        $this->assertCount(1, $registry->all());
    }

    public function testPruneRemovesOnlyRecordsOlderThanTheThreshold()
    {
        $registry = new Memory();
        $now      = time();

        $fresh = new WorkerRecord('fresh', 'host', 1, $now - 10,   $now - 10);
        $old   = new WorkerRecord('old',   'host', 2, $now - 5000, $now - 5000);

        $registry->write($fresh);
        $registry->write($old);

        $removed = $registry->prune(3600);

        $this->assertEquals(1, $removed);
        $this->assertNull($registry->read('old'));
        $this->assertNotNull($registry->read('fresh'));
    }

    public function testPruneReturnsZeroWhenNothingIsStale()
    {
        $registry = new Memory();
        $registry->write(WorkerRecord::create('worker-a'));

        $this->assertEquals(0, $registry->prune(3600));
        $this->assertCount(1, $registry->all());
    }

}
