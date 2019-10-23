<?php

namespace Pop\Queue\Test;

use Pop\Queue\Processor\Jobs\Job;
use PHPUnit\Framework\TestCase;

class JobTest extends TestCase
{

    public function testConstructor()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $this->assertEquals(1, $job->getJobId());
        $this->assertInstanceOf('Closure', $job->getCallable());
        $this->assertFalse($job->isComplete());
        $this->assertFalse($job->hasFailed());
    }

    public function testCommand()
    {
        $job = Job::command('./app help');
        $this->assertEquals('./app help', $job->getCommand());
        $this->assertTrue($job->hasCommand());
    }

    public function testExec()
    {
        $job = Job::exec('ls -la');
        $this->assertEquals('ls -la', $job->getExec());
        $this->assertTrue($job->hasExec());
    }

    public function testAttemptOnce()
    {
        $job = new Job(function(){echo 1;});
        $job->attemptOnce(true);
        $this->assertTrue($job->isAttemptOnce());
    }

    public function testRunning()
    {
        $job = new Job(function(){echo 1;});
        $job->setAsRunning();
        $this->assertTrue($job->isRunning());
    }

}