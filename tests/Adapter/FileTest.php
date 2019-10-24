<?php

namespace Pop\Queue\Test;

use Pop\Queue\Adapter;
use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{

    public function testConstructor()
    {
        $adapter = new Adapter\File(__DIR__ . '/../tmp');
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $adapter);
    }

}