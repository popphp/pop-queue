<?php

namespace Pop\Queue\Test;

use Pop\Queue\Queue;
use Pop\Queue\Manager;
use Pop\Queue\Adapter;
use Pop\Queue\Worker;
use Pop\Queue\Processor\Job;
use PHPUnit\Framework\TestCase;

class ManagerTest extends TestCase
{

    public function testConstructor()
    {
        mkdir(__DIR__ . '/tmp/test-queue');
        $fileWorker  = new Worker('test-queue1', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $fileWorker->addQueue(Queue::create(Job::create(function(){echo 123;})));
        $fileWorker->pushAll();
        $redisWorker = new Worker('test-queue2', new Adapter\Redis());
        $manager1    = Manager::create($fileWorker);
        $manager2    = new Manager([$fileWorker, $redisWorker]);
        $this->assertInstanceOf('Pop\Queue\Manager', $manager1);
        $this->assertInstanceOf('Pop\Queue\Manager', $manager2);
    }

    public function testLoad()
    {
        $adapter = new Adapter\File(__DIR__ . '/tmp/test-queue');
        $manager = Manager::load($adapter);
        $this->assertEquals(1, count($manager->getWorkers()));
    }

    public function testGetWorkers()
    {
        $fileWorker  = new Worker('test-queue1', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $redisWorker = new Worker('test-queue2', new Adapter\Redis());
        $manager     = new Manager([$fileWorker, $redisWorker]);
        $this->assertEquals(2, count($manager->getWorkers()));
    }

    public function testGetWorker()
    {
        $fileWorker = new Worker('test-queue', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager    = new Manager($fileWorker);
        $this->assertInstanceOf('Pop\Queue\Worker', $manager->getWorker('test-queue'));
    }

    public function testHasWorker()
    {
        $fileWorker = new Worker('test-queue', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager    = new Manager($fileWorker);
        $this->assertTrue($manager->hasWorker('test-queue'));
    }

    public function testMagicMethods()
    {
        $fileWorker       = new Worker('test', new Adapter\File(__DIR__ . '/tmp/test-queue'));
        $manager         = new Manager();
        $manager['test'] = $fileWorker;

        $this->assertInstanceOf('Pop\Queue\Worker', $manager['test']);
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

        $fileWorker->flushAll();
        rmdir(__DIR__ . '/tmp/test-queue');
    }

}