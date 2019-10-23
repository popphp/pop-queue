<?php

namespace Pop\Queue\Test;

use Pop\Queue\Processor;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{

    public function testGetResults()
    {
        $worker = new Processor\Worker();

        $this->assertEquals(0, count($worker->getJobResults()));
    }

}