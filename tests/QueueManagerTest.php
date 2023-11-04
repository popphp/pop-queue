<?php

namespace Pop\Queue\Test;

use Pop\Queue\Queue;
use Pop\Queue\Manager;
use Pop\Queue\Adapter;
use PHPUnit\Framework\TestCase;

class QueueManagerTest extends TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/tmp/test-queue');
        $fileQueue  = new Queue('test-queue1', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $redisQueue = new Queue('test-queue2', new Adapter\Redis());
        $manager1   = Manager::create($fileQueue);
        $manager2   = new Manager([$fileQueue, $redisQueue]);
        $this->assertInstanceOf('Pop\Queue\Manager', $manager1);
        $this->assertInstanceOf('Pop\Queue\Manager', $manager2);
    }

    public function testGetQueues()
    {
        $fileQueue  = new Queue('test-queue1', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $redisQueue = new Queue('test-queue2', new Adapter\Redis());
        $manager    = new Manager([$fileQueue, $redisQueue]);
        $this->assertEquals(2, count($manager->getQueues()));
    }

    public function testGetQueue()
    {
        $fileQueue  = new Queue('test-queue', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager    = new Manager($fileQueue);
        $this->assertInstanceOf('Pop\Queue\Queue', $manager->getQueue('test-queue'));
    }

    public function testHasQueue()
    {
        $fileQueue  = new Queue('test-queue', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager    = new Manager($fileQueue);
        $this->assertTrue($manager->hasQueue('test-queue'));
    }

    public function testMagicMethods()
    {
        $fileQueue       = new Queue('test', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager         = new Manager();
        $manager['test'] = $fileQueue;

        $this->assertInstanceOf('Pop\Queue\Queue', $manager['test']);
        $this->assertTrue(isset($manager->test));
        $this->assertTrue(isset($manager['test']));
        $this->assertEquals(1, count($manager));

        $i = 0;
        foreach ($manager as $queue) {
            $i++;
        }

        $this->assertEquals(1, $i);

        unset($manager['test']);
        $this->assertFalse(isset($manager->test));
        rmdir(__DIR__ . '/tmp/test-queue');
    }

}