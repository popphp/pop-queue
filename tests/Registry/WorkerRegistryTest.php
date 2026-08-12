<?php

namespace Pop\Queue\Test\Registry;

use Pop\Queue\Registry\Adapter\Memory;
use Pop\Queue\Registry\WorkerRecord;
use Pop\Queue\Registry\WorkerRegistry;
use PHPUnit\Framework\TestCase;

class WorkerRegistryTest extends TestCase
{

    public function testGetRegistry()
    {
        $backend  = new Memory();
        $registry = new WorkerRegistry($backend);

        $this->assertSame($backend, $registry->getRegistry());
    }

    public function testGetWorkersAndCount()
    {
        $backend = new Memory();
        $backend->write(WorkerRecord::create('worker-a'));
        $backend->write(WorkerRecord::create('worker-b'));

        $registry = new WorkerRegistry($backend);

        $this->assertCount(2, $registry->getWorkers());
        $this->assertEquals(2, $registry->countWorkers());
    }

    public function testCountIsZeroWhenEmpty()
    {
        $this->assertEquals(0, (new WorkerRegistry(new Memory()))->countWorkers());
    }

    public function testGetWorkerById()
    {
        $backend = new Memory();
        $record  = WorkerRecord::create('worker-a');
        $backend->write($record);

        $registry = new WorkerRegistry($backend);

        $this->assertEquals('worker-a', $registry->getWorker($record->getId())->getName());
        $this->assertNull($registry->getWorker('does-not-exist'));
    }

    public function testGetStaleWorkersReturnsOnlyStaleOnes()
    {
        $now     = time();
        $backend = new Memory();
        $backend->write(new WorkerRecord('fresh', 'host', 1, $now - 100, $now - 5));
        $backend->write(new WorkerRecord('stale', 'host', 2, $now - 500, $now - 300));

        $stale = (new WorkerRegistry($backend))->getStaleWorkers(90);

        $this->assertCount(1, $stale);
        $this->assertEquals('stale', reset($stale)->getId());
    }

    public function testGetStuckWorkersExcludesStaleButIdleWorkers()
    {
        $now     = time();
        $backend = new Memory();

        // Stale, but idle - wedged rather than stuck on a job.
        $backend->write(new WorkerRecord('stale-idle', 'host', 1, $now - 500, $now - 300));

        // Stale AND holding a job well past its own timeout - genuinely stuck.
        $stuck = new WorkerRecord('stuck', 'host', 2, $now - 500, $now - 300);
        $stuck->setCurrentJob('job-abc', 'billing', 30);
        $stuck->setCurrentJobStartedAt($now - 300);
        $backend->write($stuck);

        $registry = new WorkerRegistry($backend);

        $this->assertCount(2, $registry->getStaleWorkers(90));

        $stuckWorkers = $registry->getStuckWorkers(90);
        $this->assertCount(1, $stuckWorkers);
        $this->assertEquals('stuck', reset($stuckWorkers)->getId());
    }

    public function testPruneDelegatesToTheBackend()
    {
        $now     = time();
        $backend = new Memory();
        $backend->write(new WorkerRecord('fresh', 'host', 1, $now - 10,   $now - 10));
        $backend->write(new WorkerRecord('old',   'host', 2, $now - 5000, $now - 5000));

        $registry = new WorkerRegistry($backend);

        $this->assertEquals(1, $registry->prune(3600));
        $this->assertEquals(1, $registry->countWorkers());
    }

}
