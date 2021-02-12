<?php

namespace Pop\Queue\Test;

use Pop\Queue;
use Pop\Queue\Processor;
use Pop\Application;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{

    public function testConstructor()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis());
        $this->assertInstanceOf('Pop\Queue\Queue', $queue);
    }

    public function testGetName()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis());
        $this->assertEquals('pop-queue', $queue->getName());
    }

    public function testAdapter()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis());
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $queue->adapter());
    }

    public function testApplication()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis(), new Application());
        $this->assertInstanceOf('Pop\Application', $queue->application());
        $this->assertTrue($queue->hasApplication());
    }

    public function testAddWorker()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis());
        $job   = new Processor\Jobs\Job(function() {
            return 'This is job #1';
        });
        $processor = new Processor\Worker(Processor\Worker::FIFO);
        $processor->addJob($job);

        $queue->addWorker($processor);
        $this->assertTrue($queue->hasWorkers());
    }

    public function testAddWorkers()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis());
        $job   = new Processor\Jobs\Job(function() {
            return 'This is job #1';
        });
        $processor = new Processor\Worker(Processor\Worker::FIFO);
        $processor->addJob($job);

        $queue->addWorkers([$processor]);
        $this->assertEquals(1, count($queue->getWorkers()));
    }

    public function testAddScheduler()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis());
        $job   = new Processor\Jobs\Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });

        $scheduler = new Processor\Scheduler();
        $scheduler->addJob($job)
            ->every5Minutes()
            ->sundays();

        $queue->addScheduler($scheduler);
        $this->assertTrue($queue->hasSchedulers());
    }

    public function testAddSchedulers()
    {
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis());
        $job   = new Processor\Jobs\Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });

        $scheduler = new Processor\Scheduler();
        $scheduler->addJob($job)
            ->every5Minutes()
            ->sundays();

        $queue->addSchedulers([$scheduler]);
        $this->assertEquals(1, count($queue->getSchedulers()));
    }

    public function testPush()
    {
        mkdir(__DIR__ . '/tmp/pop-queue');
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\File(__DIR__ . '/tmp/'));
        $queue->clear(true);
        $job1  = new Processor\Jobs\Job(function() {
            return 'This is job #1';
        });

        $processor = new Processor\Worker(Processor\Worker::FIFO);
        $processor->addJob($job1);

        $queue->addWorker($processor);

        $job2 = new Processor\Jobs\Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });

        $scheduler = new Processor\Scheduler();
        $scheduler->addJob($job2)
            ->every5Minutes()
            ->sundays();

        $queue->addScheduler($scheduler);
        $queue->pushAll();

        $this->assertTrue($queue->hasJobs());
        $this->assertFalse($queue->hasCompletedJobs());
        $this->assertFalse($queue->hasFailedJobs());
        $this->assertEquals(2, count($queue->getJobs()));
        $this->assertEquals(0, count($queue->getCompletedJobs()));
        $this->assertEquals(0, count($queue->getFailedJobs()));

        $queue->clear(true);
        $queue->clearFailed();
        $queue->flush(true);
        $queue->flushAll();
        $queue->flushFailed();
        rmdir(__DIR__ . '/tmp/pop-queue/completed');
        rmdir(__DIR__ . '/tmp/pop-queue/failed');
        rmdir(__DIR__ . '/tmp/pop-queue');
    }

    public function testProcess()
    {
        mkdir(__DIR__ . '/tmp/pop-queue');
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\File(__DIR__ . '/tmp/'));
        $queue->clear(true);
        $job1  = new Processor\Jobs\Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });

        $processor = new Processor\Worker(Processor\Worker::FIFO);
        $processor->addJob($job1);

        $queue->addWorker($processor);

        $job2 = new Processor\Jobs\Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });

        $scheduler = new Processor\Scheduler();
        $scheduler->addJob($job2)
            ->everyMinute();

        $queue->addScheduler($scheduler);

        ob_start();
        $queue->processAll();
        $result = ob_get_clean();

        $this->assertStringContainsString('This is job #1', $result);
        $this->assertStringContainsString('This is job #2', $result);

        $queue->clear(true);
        $queue->clearFailed();
        $queue->flush(true);
        $queue->flushAll();
        $queue->flushFailed();
        //rmdir(__DIR__ . '/tmp/pop-queue/completed');
        //rmdir(__DIR__ . '/tmp/pop-queue/failed');
        //rmdir(__DIR__ . '/tmp/pop-queue');
    }

    public function testLoad()
    {
        mkdir(__DIR__ . '/tmp/pop-queue');
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\File(__DIR__ . '/tmp/'));

        $job1  = new Processor\Jobs\Job(function() {
            echo 'This is job #1' . PHP_EOL;
        }, null, 1, 'Test Job #1');

        $processor1 = new Processor\Worker(Processor\Worker::FIFO);
        $processor1->addJob($job1);

        $queue->addWorker($processor1);

        $job2 = new Processor\Jobs\Job(function() {
            echo 'This is job #2' . PHP_EOL;
        }, null, 2, 'Test Job #2');

        $processor2 = new Processor\Worker(Processor\Worker::FILO);
        $processor2->addJob($job2);

        $queue->addWorker($processor2);

        $job3 = new Processor\Jobs\Job(function() {
            echo 'This is job #3' . PHP_EOL;
        }, null, 3, 'Test Job #3');

        $scheduler = new Processor\Scheduler();
        $scheduler->addJob($job3)
            ->everyMinute();

        $queue->addScheduler($scheduler);
        $pushed = $queue->pushAll();

        $this->assertTrue(isset($pushed['1']));
        $this->assertTrue(isset($pushed['2']));
        $this->assertTrue(isset($pushed['3']));
        $this->assertEquals('Test Job #1', $pushed['1']);
        $this->assertEquals('Test Job #2', $pushed['2']);
        $this->assertEquals('Test Job #3', $pushed['3']);

        $newQueue = Queue\Queue::load('pop-queue', new Queue\Adapter\File(__DIR__ . '/tmp/'));

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
        rmdir(__DIR__ . '/tmp/pop-queue/completed');
        rmdir(__DIR__ . '/tmp/pop-queue/failed');
        rmdir(__DIR__ . '/tmp/pop-queue');
    }

}