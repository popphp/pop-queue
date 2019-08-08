<?php

namespace Pop\Queue\Test;

use Pop\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{

    public function testConstructor()
    {
        $queue = new Queue\Queue(new Queue\Adapter\Redis());
        $this->assertInstanceOf('Pop\Queue\Queue', $queue);
    }

}