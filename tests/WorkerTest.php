<?php

namespace Pop\Queue\Test;

use Pop\Application;
use Pop\Queue\Adapter\File;
use Pop\Queue\Worker;
use Pop\Queue\Queue;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{

    public function testConstructor()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue, new Application());
        $this->assertInstanceOf('Pop\Queue\Worker', $worker);
        $this->assertTrue($worker->hasQueue('pop-queue'));
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->getQueue('pop-queue'));
        $this->assertCount(1, $worker->getQueues());
        $this->assertTrue($worker->hasApplication());
        $this->assertInstanceOf('Pop\Application', $worker->getApplication());
        $this->assertInstanceOf('Pop\Application', $worker->application());
    }

    public function testAddQueues()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create([$queue1, $queue2]);
        $this->assertTrue($worker->hasQueue('pop-queue1'));
        $this->assertTrue($worker->hasQueue('pop-queue2'));
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->getQueue('pop-queue1'));
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->getQueue('pop-queue1'));
        $this->assertCount(2, $worker->getQueues());
        $this->assertEquals(2, $worker->count());
    }

    public function testMagicMethods()
    {
        $queue1 = Queue::create('pop_queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop_queue2', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create();
        $worker['pop_queue1'] = $queue1;
        $worker->pop_queue2   = $queue2;
        $this->assertCount(2, $worker->getQueues());

        $this->assertInstanceOf('Pop\Queue\Queue', $worker['pop_queue1']);
        $this->assertInstanceOf('Pop\Queue\Queue', $worker['pop_queue2']);
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->pop_queue1);
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->pop_queue2);
        $this->assertTrue(isset($worker->pop_queue1));
        $this->assertTrue(isset($worker->pop_queue2));
        $this->assertTrue(isset($worker['pop_queue1']));
        $this->assertTrue(isset($worker['pop_queue2']));
        unset($worker->pop_queue1);
        unset($worker['pop_queue2']);
        $this->assertFalse(isset($worker['pop_queue1']));
        $this->assertFalse(isset($worker['pop_queue2']));
    }

    public function testIterator()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create([$queue1, $queue2]);
        $count = 0;
        foreach ($worker as $queue) {
            if ($queue instanceof Queue) {
                $count++;
            }
        }
        $this->assertEquals(2, $count);
    }

}