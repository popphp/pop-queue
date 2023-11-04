<?php

namespace Pop\Queue\Test;

use Pop\Queue\Queue;
use Pop\Queue\Adapter;
use Pop\Queue\Processor\Worker;
use Pop\Queue\Processor\Job;
use Pop\Application;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{

    public function testConstructor()
    {
        $queue = Queue::create('pop-queue', new Adapter\Redis());
        $this->assertInstanceOf('Pop\Queue\Queue', $queue);
    }

    public function testGetName()
    {
        $queue = new Queue('pop-queue', new Adapter\Redis());
        $this->assertEquals('pop-queue', $queue->getName());
    }

    public function testAdapter()
    {
        $queue = new Queue('pop-queue', new Adapter\Redis());
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $queue->adapter());
    }

    public function testApplication()
    {
        $queue = new Queue('pop-queue', new Adapter\Redis(), new Application());
        $this->assertInstanceOf('Pop\Application', $queue->application());
        $this->assertTrue($queue->hasApplication());
    }

    public function testAddWorker()
    {
        $queue = new Queue('pop-queue', new Adapter\Redis());
        $job   = new Job(function() {
            return 'This is job #1';
        });
        $processor = new Worker(Worker::FIFO);
        $processor->addJob($job);

        $queue->addWorker($processor);
        $this->assertTrue($queue->hasWorkers());
    }

    public function testAddWorkers()
    {
        $queue = new Queue('pop-queue', new Adapter\Redis());
        $job   = new Job(function() {
            return 'This is job #1';
        });
        $processor = new Worker(Worker::FIFO);
        $processor->addJob($job);

        $queue->addWorkers([$processor]);
        $this->assertEquals(1, count($queue->getWorkers()));
    }

    public function testPush()
    {
        mkdir(__DIR__ . '/tmp/pop-queue');
        $queue = new Queue('pop-queue', new Adapter\File(__DIR__ . '/tmp/'));
        $queue->clear(true);
        $job1  = new Job(function() {
            return 'This is job #1';
        });

        $processor = new Worker(Worker::FIFO);
        $processor->addJob($job1);

        $queue->addWorker($processor);

        $queue->pushAll();

        $this->assertTrue($queue->hasJobs());
        $this->assertFalse($queue->hasCompletedJobs());
        $this->assertFalse($queue->hasFailedJobs());
        $this->assertEquals(1, count($queue->getJobs()));
        $this->assertEquals(0, count($queue->getCompletedJobs()));
        $this->assertEquals(0, count($queue->getFailedJobs()));

        $queue->clear(true);
        $queue->clearFailed();
        $queue->flush(true);
        $queue->flushAll();
        $queue->flushFailed();
//        rmdir(__DIR__ . '/tmp/pop-queue/completed');
//        rmdir(__DIR__ . '/tmp/pop-queue/failed');
//        rmdir(__DIR__ . '/tmp/pop-queue');
    }

    public function testProcess()
    {
        mkdir(__DIR__ . '/tmp/pop-queue');
        $queue = new Queue('pop-queue', new Adapter\File(__DIR__ . '/tmp/'));
        $queue->clear(true);
        $job1  = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });

        $processor = new Worker(Worker::FIFO);
        $processor->addJob($job1);

        $queue->addWorker($processor);

        ob_start();
        $queue->processAll();
        $result = ob_get_clean();

        $this->assertStringContainsString('This is job #1', $result);

        $queue->clear(true);
        $queue->clearFailed();
        $queue->flush(true);
        $queue->flushAll();
        $queue->flushFailed();
    }

    public function testLoad()
    {
        mkdir(__DIR__ . '/tmp/pop-queue');
        $queue = new Queue('pop-queue', new Adapter\File(__DIR__ . '/tmp/'));

        $job1  = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });

        $processor1 = new Worker(Worker::FIFO);
        $processor1->addJob($job1);

        $queue->addWorker($processor1);

        $job2 = new Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });

        $processor2 = new Worker(Worker::FILO);
        $processor2->addJob($job2);

        $queue->addWorker($processor2);

        $pushed = $queue->pushAll();

        $this->assertTrue(array_key_exists($job1->getJobId(), $pushed));
        $this->assertTrue(array_key_exists($job2->getJobId(), $pushed));

        $newQueue = Queue::load('pop-queue', new Adapter\File(__DIR__ . '/tmp/'));

        $this->assertInstanceOf('Pop\Queue\Queue', $newQueue);
        $this->assertTrue($queue->isQueued($job1->getJobId()));
        $this->assertTrue($queue->hasJob($job1->getJobId()));
        $this->assertNotEmpty($queue->getJob($job1->getJobId()));
        $this->assertFalse($queue->isCompleted($job1->getJobId()));
        $this->assertFalse($queue->hasFailed($job1->getJobId()));
        $this->assertFalse($queue->hasCompletedJob($job1->getJobId()));
        $this->assertFalse($queue->hasFailedJob($job1->getJobId()));
        $this->assertEmpty($queue->getCompletedJob($job1->getJobId()));
        $this->assertEmpty($queue->getFailedJob($job1->getJobId()));

        $newQueue->clear(true);
        $newQueue->clearFailed();
        $newQueue->flush(true);
        $newQueue->flushAll();
        $newQueue->flushFailed();
//        rmdir(__DIR__ . '/tmp/pop-queue/completed');
//        rmdir(__DIR__ . '/tmp/pop-queue/failed');
//        rmdir(__DIR__ . '/tmp/pop-queue');
    }

}