<?php

namespace Pop\Queue\Test;

use Pop\Queue;
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
    }

}