<?php

namespace Pop\Queue\Test;

use Pop\Queue\Queue;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{

    public function testConstructor()
    {
        $queue = new Queue();
        $this->assertInstanceOf('Pop\Queue\Queue', $queue);
    }

}