<?php

namespace Pop\Queue\Test;

use Pop\Queue\Adapter;
use Pop\Db;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{

    public function testConstructor()
    {
        touch(__DIR__ . '/../tmp/test.sqlite');
        chmod(__DIR__ . '/../tmp/test.sqlite', 0777);
        $db = Db\Db::sqliteConnect([
            'database' => __DIR__ . '/../tmp/test.sqlite'
        ]);
        $adapter = new Adapter\Db($db);
        $this->assertInstanceOf('Pop\Queue\Adapter\Db', $adapter);
        unlink(__DIR__ . '/../tmp/test.sqlite');
    }

}