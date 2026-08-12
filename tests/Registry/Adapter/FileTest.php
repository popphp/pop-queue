<?php

namespace Pop\Queue\Test\Registry\Adapter;

use Pop\Queue\Registry\Adapter\File;
use Pop\Queue\Registry\WorkerRecord;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{

    protected function tearDown(): void
    {
        // Leave the fixture directory itself in place, but clear records
        // between tests so ordering can't leak state.
        (new File(__DIR__ . '/../../tmp/pop-registry'))->prune(-1);
    }

    public function testConstructorThrowsOnMissingFolder()
    {
        $this->expectException('Pop\Queue\Registry\Exception');
        new File(__DIR__ . '/../../tmp/does-not-exist');
    }

    public function testWriteAndRead()
    {
        $registry = new File(__DIR__ . '/../../tmp/pop-registry');
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
        $registry = new File(__DIR__ . '/../../tmp/pop-registry');
        $this->assertNull($registry->read('does-not-exist'));
    }

    public function testWriteIsAnUpsert()
    {
        $registry = new File(__DIR__ . '/../../tmp/pop-registry');
        $record   = WorkerRecord::create('worker-a');

        $registry->write($record);
        $record->incrementProcessed();
        $registry->write($record);

        $this->assertCount(1, $registry->all());
        $this->assertEquals(1, $registry->read($record->getId())->getJobsProcessed());
    }

    public function testAllReturnsEveryRecord()
    {
        $registry = new File(__DIR__ . '/../../tmp/pop-registry');
        $registry->write(WorkerRecord::create('worker-a'));
        $registry->write(WorkerRecord::create('worker-b'));

        $this->assertCount(2, $registry->all());
    }

    public function testDelete()
    {
        $registry = new File(__DIR__ . '/../../tmp/pop-registry');
        $record   = WorkerRecord::create('worker-a');
        $registry->write($record);

        $registry->delete($record->getId());

        $this->assertNull($registry->read($record->getId()));
        $this->assertEmpty($registry->all());
    }

    public function testPruneRemovesOnlyRecordsOlderThanTheThreshold()
    {
        $registry = new File(__DIR__ . '/../../tmp/pop-registry');
        $now      = time();

        $registry->write(new WorkerRecord('fresh-aaa', 'host', 1, $now - 10,   $now - 10));
        $registry->write(new WorkerRecord('old-bbb',   'host', 2, $now - 5000, $now - 5000));

        $removed = $registry->prune(3600);

        $this->assertEquals(1, $removed);
        $this->assertNull($registry->read('old-bbb'));
        $this->assertNotNull($registry->read('fresh-aaa'));
    }

    public function testCorruptRecordFileIsSkippedRatherThanFatal()
    {
        $folder = __DIR__ . '/../../tmp/pop-registry';
        file_put_contents($folder . '/worker-not-valid-json.json', 'not valid json at all');

        $registry = new File($folder);
        $registry->write(WorkerRecord::create('worker-a'));

        // The corrupt file must not derail enumeration of the good record.
        $this->assertCount(1, $registry->all());

        unlink($folder . '/worker-not-valid-json.json');
    }

    public function testWriteStoresASnapshotNotAReferenceToTheCallersObject()
    {
        $registry = new File(__DIR__ . '/../../tmp/pop-registry');
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
