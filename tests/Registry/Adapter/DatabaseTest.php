<?php

namespace Pop\Queue\Test\Registry\Adapter;

use Pop\Db\Db as PopDb;
use Pop\Queue\Registry\Adapter\Database;
use Pop\Queue\Registry\WorkerRecord;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{

    protected function db()
    {
        touch(__DIR__ . '/../../tmp/test.sqlite');
        chmod(__DIR__ . '/../../tmp/test.sqlite', 0777);

        return PopDb::sqliteConnect(['database' => __DIR__ . '/../../tmp/test.sqlite']);
    }

    protected function registry(): Database
    {
        $registry = new Database($this->db());
        $registry->prune(-1); // clear between tests
        return $registry;
    }

    public function testConstructorCreatesTable()
    {
        $db       = $this->db();
        $registry = new Database($db);

        $this->assertEquals('pop_worker_registry', $registry->getTable());
        $this->assertTrue($db->hasTable('pop_worker_registry'));
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
