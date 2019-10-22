<?php

namespace Pop\Queue\Test;

use Pop\Queue;
use PHPUnit\Framework\TestCase;

class QueueManagerTest extends TestCase
{

    public function testConstructor()
    {
        $fileQueue  = new Queue\Queue('test-queue1', new Queue\Adapter\File(__DIR__ . '/tmp/test-queue'));
        $redisQueue = new Queue\Queue('test-queue2', new Queue\Adapter\Redis());
        $manager1   = new Queue\Manager($fileQueue);
        $manager2   = new Queue\Manager([$fileQueue, $redisQueue]);
        $this->assertInstanceOf('Pop\Queue\Manager', $manager1);
        $this->assertInstanceOf('Pop\Queue\Manager', $manager2);
    }

    public function testGetQueues()
    {
        $fileQueue  = new Queue\Queue('test-queue1', new Queue\Adapter\File(__DIR__ . '/tmp/test-queue'));
        $redisQueue = new Queue\Queue('test-queue2', new Queue\Adapter\Redis());
        $manager    = new Queue\Manager([$fileQueue, $redisQueue]);
        $this->assertEquals(2, count($manager->getQueues()));
    }

    public function testGetQueue()
    {
        $fileQueue  = new Queue\Queue('test-queue', new Queue\Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager    = new Queue\Manager($fileQueue);
        $this->assertInstanceOf('Pop\Queue\Queue', $manager->getQueue('test-queue'));
    }

    public function testHasQueue()
    {
        $fileQueue  = new Queue\Queue('test-queue', new Queue\Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager    = new Queue\Manager($fileQueue);
        $this->assertTrue($manager->hasQueue('test-queue'));
    }

    public function testMagicMethods()
    {
        $fileQueue       = new Queue\Queue('test', new Queue\Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager         = new Queue\Manager();
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
    }

}