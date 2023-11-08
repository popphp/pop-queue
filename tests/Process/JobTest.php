<?php

namespace Pop\Queue\Test\Process;

use Pop\Application;
use Pop\Queue\Process\Job;
use PHPUnit\Framework\TestCase;
use Pop\Utils\CallableObject;

class JobTest extends TestCase
{

    public function testConstructor()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $this->assertEquals(1, $job->getJobId());
        $this->assertInstanceOf('Pop\Utils\CallableObject', $job->getCallable());
        $this->assertInstanceOf('Closure', $job->getCallable()->getCallable());
        $this->assertFalse($job->isComplete());
        $this->assertFalse($job->hasFailed());
    }

    public function testCreate()
    {
        $job = Job::create(function(){echo 1;}, null, 1);
        $this->assertEquals(1, $job->getJobId());
        $this->assertInstanceOf('Pop\Utils\CallableObject', $job->getCallable());
        $this->assertInstanceOf('Closure', $job->getCallable()->getCallable());
        $this->assertFalse($job->isComplete());
        $this->assertFalse($job->hasFailed());
    }

    public function testSetJobDescription()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->setJobDescription('This is a test');
        $this->assertTrue($job->hasJobDescription());
        $this->assertEquals('This is a test', $job->getJobDescription());
    }

    public function testSetMaxAttempts()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->setMaxAttempts(1);
        $this->assertTrue($job->hasMaxAttempts());
        $this->assertTrue($job->isAttemptOnce());
        $this->assertFalse($job->hasAttempts());
        $this->assertFalse($job->hasExceededMaxAttempts());
        $this->assertEquals(1, $job->getMaxAttempts());
    }

    public function testExceededMaxAttempts()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->setMaxAttempts(0);
        $this->assertFalse($job->hasExceededMaxAttempts());
    }

    public function testRunUntil1()
    {
        $dateTime = date('Y-m-d H:i:s', time() + 10000000);
        $job = new Job(function(){echo 1;}, null, 1);
        $job->runUntil($dateTime);
        $this->assertTrue($job->hasRunUntil());
        $this->assertEquals($dateTime, $job->getRunUntil());
        $this->assertFalse($job->isExpired());
        $this->assertTrue($job->hasNotRun());
    }

    public function testRunUntil2()
    {
        $dateTime = time() + 10000000;
        $job = new Job(function(){echo 1;}, null, 1);
        $job->runUntil($dateTime);
        $this->assertTrue($job->hasRunUntil());
        $this->assertEquals($dateTime, $job->getRunUntil());
        $this->assertFalse($job->isExpired());
    }

    public function testStart()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->start();
        $this->assertTrue($job->hasStarted());
        $this->assertTrue($job->isRunning());
        $this->assertNotEmpty($job->getStarted());
    }

    public function testFailed()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->failed();
        $this->assertTrue($job->hasFailed());
        $this->assertNotEmpty($job->getFailed());
    }

    public function testSetCallableObject1()
    {
        $callable = new CallableObject(function($var){echo $var;});
        $job = new Job();
        $job->setCallable($callable, 'Hello');
        $this->assertInstanceOf('Pop\Utils\CallableObject', $job->getCallable());
        $this->assertInstanceOf('Closure', $job->getCallable()->getCallable());
    }

    public function testSetCallableObject2()
    {
        $callable = new CallableObject(function($var1, $var2){echo $var1 . ' ' . $var2;});
        $job = new Job();
        $job->setCallable($callable, ['Hello', 'World']);
        $this->assertInstanceOf('Pop\Utils\CallableObject', $job->getCallable());
        $this->assertInstanceOf('Closure', $job->getCallable()->getCallable());
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

    public function testRunExec()
    {
        $job = Job::exec('ls -la');
        $this->assertIsArray($job->run());
    }

    public function testRunCommand()
    {
        $job = Job::command('hello');

        $app = new Application([
            'routes' => [
                'hello' => function(){
                    echo 'Hello World!';
                }
            ]
        ]);
        $result = $job->run($app);
        $this->assertIsArray($result);
        $this->assertTrue(isset($result[0]));
        $this->assertEquals('Hello World!', $result[0]);
    }

    public function testRunCommandWithNoCommand()
    {
        $job = Job::command('foo');

        $app = new Application([
            'routes' => [
                'hello' => function(){
                    echo 'Hello World!';
                }
            ]
        ]);
        $result = $job->run($app);
        $this->assertFalse($result);
    }

    public function testRunWithNone()
    {
        $job = new Job();
        $this->assertNull($job->run());
    }

}