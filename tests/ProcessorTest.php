<?php

namespace Pop\Queue\Test;

use Pop\Queue\Processor;
use PHPUnit\Framework\TestCase;

class ProcessorTest extends TestCase
{

    public function testGetJobResults()
    {
        $worker = new Processor\Worker();

        $this->assertEquals(0, count($worker->getJobResults()));
        $this->assertEmpty($worker->getJobResult(0));
        $this->assertFalse($worker->hasJobResults());
    }

    public function testGetCompletedJobResults()
    {
        $worker = new Processor\Worker();

        $this->assertEquals(0, count($worker->getCompletedJobs()));
        $this->assertEmpty($worker->getCompletedJob(0));
        $this->assertFalse($worker->hasCompletedJobs());
    }

    public function testGetFailedJobResults()
    {
        $worker = new Processor\Worker();

        $this->assertEquals(0, count($worker->getFailedJobs()));
        $this->assertEmpty($worker->getFailedJob(0));
        $this->assertFalse($worker->hasFailedJobs());
    }

    public function testGetFailedExceptions()
    {
        $worker = new Processor\Worker();

        $this->assertEquals(0, count($worker->getFailedExceptions()));
        $this->assertEmpty($worker->getFailedException(0));
        $this->assertFalse($worker->hasFailedExceptions());
    }

}