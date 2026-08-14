<?php

namespace Pop\Queue\Test\Process;

use Pop\Application;
use Pop\Queue\Process\Job;
use PHPUnit\Framework\TestCase;
use Pop\Utils\CallableObject;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

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

    /**
     * A plain class queued by 'Class->method' string - the class-based job
     * form documented in the README. Needs no route, and unlike a Command
     * object it stays small on the wire, so it must keep working.
     */
    public function testCallableInstanceMethodClassString()
    {
        $job    = Job::create('Pop\Queue\Test\TestAsset\DigestService->handle', ['nick@test.com']);
        $result = $job->run(new Application(['foo' => 'bar']));

        $this->assertEquals('sent to nick@test.com (app: yes)', $result);
    }

    public function testCallableStaticMethodClassString()
    {
        $job    = Job::create('Pop\Queue\Test\TestAsset\DigestService::handleStatic', ['nick@test.com']);
        $result = $job->run(new Application(['foo' => 'bar']));

        $this->assertEquals('static sent to nick@test.com', $result);
    }

    /**
     * A bare class-string constructs the class with the parameters and the
     * constructed object becomes the job's results.
     */
    public function testCallableBareClassStringCallsConstructor()
    {
        $job    = Job::create('Pop\Queue\Test\TestAsset\DigestService', ['nick@test.com']);
        $result = $job->run(new Application(['foo' => 'bar']));

        $this->assertInstanceOf('Pop\Queue\Test\TestAsset\DigestService', $result);
        $this->assertEquals('nick@test.com', $result->constructedWith);
    }

    /**
     * The reason to prefer a class-string over queueing a rich object: the
     * stored payload stays tiny. A pop-console Command instance serializes
     * to ~6KB because it carries a Console along with it.
     */
    public function testCallableClassStringJobSerializesToASmallPayload()
    {
        $job = Job::create('Pop\Queue\Test\TestAsset\DigestService->handle', ['nick@test.com']);

        $revived = unserialize(serialize($job));
        $this->assertLessThan(2048, strlen(serialize($job)));
        $this->assertEquals(
            'sent to nick@test.com (app: yes)',
            $revived->run(new Application(['foo' => 'bar']))
        );
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

    public function testExecArray()
    {
        $job = Job::exec(['ls', '-la']);
        $this->assertEquals(['ls', '-la'], $job->getExec());
        $this->assertTrue($job->hasExec());
    }

    public function testRunExecArray()
    {
        $job = Job::exec(['ls', '-la']);
        $this->assertIsArray($job->run());
    }

    public function testRunExecPreservesLiteralZeroOutputLine()
    {
        $job     = Job::exec('echo 0');
        $results = $job->run();

        $this->assertEquals(['0'], $results);
    }

    public function testRunExecThrowsOnFailingCommand()
    {
        $job = Job::exec('exit 1');
        $this->expectException(ProcessFailedException::class);
        $job->run();
    }

    public function testRunExecArrayThrowsOnFailingCommand()
    {
        $job = Job::exec(['false']);
        $this->expectException(ProcessFailedException::class);
        $job->run();
    }

    public function testRunExecTimeoutThrowsProcessTimedOutException()
    {
        $job = Job::exec(['sleep', '3']);
        $job->setTimeout(1);

        $start = microtime(true);

        try {
            $job->run();
            $this->fail('Expected ProcessTimedOutException was not thrown.');
        } catch (ProcessTimedOutException $e) {
            $elapsed = microtime(true) - $start;
            // Proves the child process was actually interrupted around the
            // 1-second configured timeout, not left to run the full 3
            // seconds while PHP just moved on.
            $this->assertLessThan(2.5, $elapsed);
        }
    }

    public function testExecProcessHasNoTimeoutByDefault()
    {
        $job = new class extends Job {
            public function exposeExecProcess()
            {
                return $this->buildExecProcess();
            }
        };
        $job->setExec(['sleep', '0.1']);

        // Symfony\Process defaults to a 60-second timeout on every instance
        // unless told otherwise - a job with no configured timeout must get
        // that disabled entirely (null), matching exec()'s old
        // no-timeout-by-default behavior. This is a fast, deterministic
        // check on the constructed-but-not-yet-run Process object, not a
        // 60+ second test.
        $this->assertNull($job->exposeExecProcess()->getTimeout());

        $job->setTimeout(5);
        $this->assertEquals(5.0, $job->exposeExecProcess()->getTimeout());
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

    public function testCallableWithApplication1()
    {
        $job = Job::create(function($application) {
            return $application->config['foo'];
        });

        $app = new Application(['foo' => 'bar']);
        $result = $job->run($app);
        $this->assertEquals('bar', $result);
    }

    public function testCallableWithApplication2()
    {
        $job = Job::create(new CallableObject(function($application, $param) {
            return $param . ':' . $application->config['foo'];
        }), '123');

        $app = new Application(['foo' => 'bar']);
        $result = $job->run($app);
        $this->assertTrue($job->hasResults());
        $this->assertEquals('123:bar', $result);
        $this->assertEquals('123:bar', $job->getResults());
    }

    public function testDelaySeconds()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $before = time();
        $job->delay(60);
        $this->assertTrue($job->getAvailableAt() >= $before + 60);
        $this->assertFalse($job->isAvailable());
    }

    public function testDelayTimestamp()
    {
        $future = time() + 10000000;
        $job = new Job(function(){echo 1;}, null, 1);
        $job->delay($future);
        $this->assertEquals($future, $job->getAvailableAt());
        $this->assertFalse($job->isAvailable());
    }

    public function testDelayDateString()
    {
        $future = date('Y-m-d H:i:s', time() + 10000000);
        $job = new Job(function(){echo 1;}, null, 1);
        $job->delay($future);
        $this->assertEquals(strtotime($future), $job->getAvailableAt());
    }

    public function testDelayInvalidString()
    {
        $this->expectException('Pop\Queue\Process\Exception');
        $job = new Job(function(){echo 1;}, null, 1);
        $job->delay('not a valid date');
    }

    public function testNoDelayIsImmediatelyAvailable()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $this->assertNull($job->getAvailableAt());
        $this->assertTrue($job->isAvailable());
    }

    public function testTimeout()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $this->assertFalse($job->hasTimeout());
        $job->setTimeout(30);
        $this->assertTrue($job->hasTimeout());
        $this->assertEquals(30, $job->getTimeout());
    }

    public function testBackoffFixed()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->setBackoff(15);
        $this->assertEquals(15, $job->getBackoff());
        $job->failed();
        $this->assertEquals(15, $job->getBackoffDelay());
        $job->failed();
        $this->assertEquals(15, $job->getBackoffDelay());
    }

    public function testBackoffSchedule()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->setBackoff([10, 30, 60]);
        // getBackoff() returns the configured schedule as given; getBackoffDelay()
        // resolves it against the current attempt count.
        $this->assertEquals([10, 30, 60], $job->getBackoff());
        $job->failed();
        $this->assertEquals(10, $job->getBackoffDelay());
        $job->failed();
        $this->assertEquals(30, $job->getBackoffDelay());
        $job->failed();
        $this->assertEquals(60, $job->getBackoffDelay());
        $job->failed();
        $this->assertEquals(60, $job->getBackoffDelay());
    }

    public function testNoBackoffIsImmediateRetry()
    {
        $job = new Job(function(){echo 1;}, null, 1);
        $job->failed();
        $this->assertFalse($job->hasBackoff());
        $this->assertNull($job->getBackoff());
        $this->assertEquals(0, $job->getBackoffDelay());
    }

    public function testGetDurationIsNullBeforeRunning()
    {
        $job = Job::create(function(){ return 1; });
        $this->assertNull($job->getDuration());
    }

    public function testGetDurationIsNullWhileStillRunning()
    {
        $job = Job::create(function(){ return 1; });
        $job->start();

        // Started but never completed - there is no duration yet.
        $this->assertNull($job->getDuration());
    }

    public function testGetDurationAfterCompleting()
    {
        $job = Job::create(function(){ return 1; });
        $job->start();
        $job->complete();

        $duration = $job->getDuration();
        $this->assertIsInt($duration);
        $this->assertGreaterThanOrEqual(0, $duration);
    }

    public function testGetDurationIsNullWhenCompletedWithoutStarting()
    {
        $job = Job::create(function(){ return 1; });

        // complete() has no guard requiring start() first, so this state is
        // reachable - and a duration computed from a null start would be
        // nonsense, so the contract is null.
        $job->complete();

        $this->assertNull($job->getDuration());
    }

}