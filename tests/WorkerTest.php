<?php

namespace Pop\Queue\Test;

use Pop\Queue\Queue;
use Pop\Queue\Adapter;
use Pop\Queue\Worker;
use Pop\Queue\Processor\Job;
use Pop\Application;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{

    public function testConstructor()
    {
        $worker = Worker::create('pop-worker', new Adapter\Redis());
        $this->assertInstanceOf('Pop\Queue\Worker', $worker);
    }

    public function testGetName()
    {
        $worker = new Worker('pop-worker', new Adapter\Redis());
        $this->assertEquals('pop-worker', $worker->getName());
    }

    public function testAdapter()
    {
        $worker = new Worker('pop-worker', new Adapter\Redis());
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $worker->adapter());
    }

    public function testApplication()
    {
        $worker = new Worker('pop-worker', new Adapter\Redis(), new Application());
        $this->assertInstanceOf('Pop\Application', $worker->application());
        $this->assertTrue($worker->hasApplication());
    }

    public function testAddWorker()
    {
        $worker = new Worker('pop-worker', new Adapter\Redis());
        $job    = new Job(function() {
            return 'This is job #1';
        });
        $queue = new Queue(Queue::FIFO);
        $queue->addJob($job);

        $worker->addQueue($queue);
        $this->assertTrue($worker->hasQueues());
    }

    public function testAddWorkers()
    {
        $worker = new Worker('pop-worker', new Adapter\Redis());
        $job   = new Job(function() {
            return 'This is job #1';
        });
        $queue = new Queue(Queue::FIFO);
        $queue->addJob($job);

        $worker->addQueues([$queue]);
        $this->assertEquals(1, count($worker->getQueues()));
    }

    public function testPush()
    {
        mkdir(__DIR__ . '/tmp/pop-worker');
        $worker = new Worker('pop-worker', new Adapter\File(__DIR__ . '/tmp/'));
        $worker->clear(true);
        $job1  = new Job(function() {
            return 'This is job #1';
        });

        $queue = new Queue(Queue::FIFO);
        $queue->addJob($job1);

        $worker->addQueue($queue);

        $worker->pushAll();

        $this->assertTrue($worker->hasJobs());
        $this->assertFalse($worker->hasCompletedJobs());
        $this->assertFalse($worker->hasFailedJobs());
        $this->assertEquals(1, count($worker->getJobs()));
        $this->assertEquals(0, count($worker->getCompletedJobs()));
        $this->assertEquals(0, count($worker->getFailedJobs()));

        $worker->clear(true);
        $worker->clearFailed();
        $worker->flush(true);
        $worker->flushAll();
        $worker->flushFailed();
    }

    public function testProcess()
    {
        mkdir(__DIR__ . '/tmp/pop-worker');
        $worker = new Worker('pop-worker', new Adapter\File(__DIR__ . '/tmp/'));
        $worker->clear(true);
        $job1  = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });

        $queue = new Queue(Queue::FIFO);
        $queue->addJob($job1);

        $worker->addQueue($queue);

        ob_start();
        $worker->processAll();
        $result = ob_get_clean();

        $this->assertStringContainsString('This is job #1', $result);

        $worker->clear(true);
        $worker->clearFailed();
        $worker->flush(true);
        $worker->flushAll();
        $worker->flushFailed();
    }

    public function testLoad()
    {
        mkdir(__DIR__ . '/tmp/pop-worker');
        $worker = new Worker('pop-worker', new Adapter\File(__DIR__ . '/tmp/'));

        $job1  = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });

        $queue1 = new Queue(Queue::FIFO);
        $queue1->addJob($job1);

        $worker->addQueue($queue1);

        $job2 = new Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });

        $queue2 = new Queue(Queue::FILO);
        $queue2->addJob($job2);

        $worker->addQueue($queue2);

        $pushed = $worker->pushAll();

        $this->assertTrue(array_key_exists($job1->getJobId(), $pushed));
        $this->assertTrue(array_key_exists($job2->getJobId(), $pushed));

        $newWorker = Worker::load('pop-worker', new Adapter\File(__DIR__ . '/tmp/'));

        $this->assertInstanceOf('Pop\Queue\Worker', $newWorker);
        $this->assertTrue($worker->isQueued($job1->getJobId()));
        $this->assertTrue($worker->hasJob($job1->getJobId()));
        $this->assertNotEmpty($worker->getJob($job1->getJobId()));
        $this->assertFalse($worker->isCompleted($job1->getJobId()));
        $this->assertFalse($worker->hasFailed($job1->getJobId()));
        $this->assertFalse($worker->hasCompletedJob($job1->getJobId()));
        $this->assertFalse($worker->hasFailedJob($job1->getJobId()));
        $this->assertEmpty($worker->getCompletedJob($job1->getJobId()));
        $this->assertEmpty($worker->getFailedJob($job1->getJobId()));

        $newWorker->clear(true);
        $newWorker->clearFailed();
        $newWorker->flush(true);
        $newWorker->flushAll();
        $newWorker->flushFailed();
    }

}