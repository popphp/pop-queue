<?php

namespace Pop\Queue\Test;

use Pop\Application;
use Pop\Queue\Processor\Jobs\Job;
use PHPUnit\Framework\TestCase;
use Pop\Utils\CallableObject;

class JobTest extends TestCase
{

    public function testConstructor()
    {
        $job = new Job(function(){echo 1;}, null, 1, 'Test Desc');
        $this->assertEquals(1, $job->getJobId());
        $this->assertEquals('Test Desc', $job->getJobDescription());
        $this->assertTrue($job->hasJobDescription());
        $this->assertInstanceOf('Pop\Utils\CallableObject', $job->getCallable());
        $this->assertInstanceOf('Closure', $job->getCallable()->getCallable());
        $this->assertFalse($job->isComplete());
        $this->assertFalse($job->hasFailed());
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