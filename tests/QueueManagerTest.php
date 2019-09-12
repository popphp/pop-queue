<?php

namespace Pop\Queue\Test;

use Pop\Queue;
use PHPUnit\Framework\TestCase;

class QueueManagerTest extends TestCase
{

    public function testConstructor()
    {
        $manager = new Queue\Manager();
        $this->assertInstanceOf('Pop\Queue\Manager', $manager);
    }

}