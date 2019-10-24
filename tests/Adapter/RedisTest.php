<?php

namespace Pop\Queue\Test;

use Pop\Queue\Adapter;
use PHPUnit\Framework\TestCase;

class RedisTest extends TestCase
{

    public function testConstructor()
    {
        $adapter = new Adapter\Redis();
        $this->assertInstanceOf('Pop\Queue\Adapter\Redis', $adapter);
    }

}