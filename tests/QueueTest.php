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
        $queue = new Queue\Queue('pop-queue', new Queue\Adapter\Redis(), new Application());
        $job   = new Processor\Jobs\Job(function() {
            return 'This is job #1';
        });
        $processor = new Processor\Worker(Processor\Worker::FIFO);
        $processor->addJob($job);

        $queue->addWorker($processor);
    }

}