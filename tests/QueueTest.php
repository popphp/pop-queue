<?php

namespace Pop\Queue\Test;

use Pop\Queue\Adapter\File;
use Pop\Queue\Queue;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{

    public function testConstructor()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'), 'FIFO');
        $this->assertInstanceOf('Pop\Queue\Queue', $queue);
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $queue->getAdapter());
        $this->assertInstanceOf('Pop\Queue\Adapter\File', $queue->adapter());
        $this->assertTrue($queue->hasName());
        $this->assertEquals('pop-queue', $queue->getName());
        $this->assertEquals('FIFO', $queue->getPriority());
    }

    public function testPriority()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'), 'FIFO');
        $this->assertTrue($queue->isFifo());
        $this->assertFalse($queue->isFilo());
        $this->assertTrue($queue->isLilo());
        $this->assertFalse($queue->isLifo());
    }

    public function testAddJobs()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'), 'FIFO');
        $job1 = Job::create(function(){
            echo 'Job #1' . PHP_EOL;
        });
        $job2 = Job::create(function(){
            echo 'Job #1' . PHP_EOL;
        });
        $queue->addJobs([$job1, $job2], 3);
        $this->assertTrue($queue->adapter()->hasJobs());
        $queue->clear();
    }

    public function testAddTasks()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'), 'FIFO');
        $task1 = Task::create(function(){
            echo 'Task #1' . PHP_EOL;
        })->everyMinute();
        $queue->addTasks([$task1], 5);
        $this->assertTrue($queue->adapter()->hasTasks());
        $queue->clearTasks();
        $queue->clearFailed();
        $queue->clear();
    }

    public function testWorkNoJobs()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $this->assertNull($queue->work());
    }

    public function testWorkJob()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });

        $queue->addJob($job);
        $job = $queue->work();
        $this->assertTrue($job->isComplete());
        $this->assertFalse($queue->adapter()->hasJobs());
        $queue->clear();
   }

    public function testWorkFailedJobStillRetryable()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            throw new \Exception('Error!');
        });

        $queue->addJob($job);
        $job = $queue->work();
        $this->assertTrue($job->hasFailed());
        $this->assertTrue($job->isValid());
        $this->assertTrue($queue->adapter()->hasJobs());
        $this->assertFalse($queue->adapter()->hasDeadJobs());
        $queue->clear();
    }

    public function testWorkJobExhaustsRetries()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            throw new \Exception('Error!');
        });
        $job->setMaxAttempts(1);

        $queue->addJob($job);
        $job = $queue->work();
        $this->assertTrue($job->hasFailed());
        $this->assertFalse($job->isValid());
        $this->assertFalse($queue->adapter()->hasJobs());
        $this->assertTrue($queue->adapter()->hasDeadJobs());
        $this->assertCount(1, $queue->adapter()->getDeadJobs());
        $queue->clearFailed();
    }

    public function testWorkJobCatchesError()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            throw new \TypeError('Bad type!');
        });
        $job->setMaxAttempts(1);

        $queue->addJob($job);
        $job = $queue->work();
        $this->assertTrue($job->hasFailed());
        $this->assertEquals('Bad type!', $job->getFailedMessages()[$job->getFailed()]);
        $queue->clearFailed();
    }

    public function testWorkDelayedJobNotYetAvailable()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });
        $job->delay(60);

        $queue->addJob($job);
        $this->assertNull($queue->work());
        $queue->clear();
    }

    public function testWorkJobTimeout()
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('The pcntl extension is not available.');
        }

        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            sleep(3);
            return 'done';
        });
        $job->setTimeout(1);
        $job->setMaxAttempts(1);

        $queue->addJob($job);
        $job = $queue->work();
        $this->assertTrue($job->hasFailed());
        $queue->clearFailed();
    }

    public function testRunTask1()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $task  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute();

        $queue->addTask($task);

        $tasks = $queue->run();
        $this->assertTrue(is_array($tasks));
        $queue->clearTasks();
    }

    public function testRunTask2()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $task1  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->every15Seconds();

        $task2  = Task::create(function(){
            throw new \Exception('Error!');
        })->every30Seconds();

        $queue->addTasks([$task1, $task2]);

        $tasks = $queue->run();
        $this->assertTrue(is_array($tasks));
        $queue->clearTasks();
        $queue->clear();
    }

    public function testEvaluateTasksOnceRunsAllDueSubMinuteTasksInOnePass()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));

        $task1 = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everySecond();
        $task2 = Task::create(function(){
            return 'Task #2' . PHP_EOL;
        })->everySecond();

        $method = new \ReflectionMethod($queue, 'evaluateTasksOnce');
        $method->setAccessible(true);

        $ran = $method->invoke($queue, [
            $task1->getJobId() => $task1,
            $task2->getJobId() => $task2,
        ], null, false);

        $this->assertArrayHasKey($task1->getJobId(), $ran);
        $this->assertArrayHasKey($task2->getJobId(), $ran);
    }

    public function testEvaluateTasksOnceOnlySubMinuteSkipsCoarseTasks()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));

        $subMinuteTask = Task::create(function(){
            return 'sub-minute' . PHP_EOL;
        })->everySecond();
        $coarseTask = Task::create(function(){
            return 'coarse' . PHP_EOL;
        })->everyMinute()->setBuffer(-1);

        $method = new \ReflectionMethod($queue, 'evaluateTasksOnce');
        $method->setAccessible(true);

        $ran = $method->invoke($queue, [
            $subMinuteTask->getJobId() => $subMinuteTask,
            $coarseTask->getJobId()    => $coarseTask,
        ], null, true);

        $this->assertArrayHasKey($subMinuteTask->getJobId(), $ran);
        $this->assertArrayNotHasKey($coarseTask->getJobId(), $ran);
    }

    public function testRunEvaluatesBothSubMinuteTasksOnTheSharedFirstTick()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));

        $fireTimes = [];
        $task1 = Task::create(function() use (&$fireTimes){
            $fireTimes['task1'] = microtime(true);
        })->everySecond();
        $task2 = Task::create(function() use (&$fireTimes){
            $fireTimes['task2'] = microtime(true);
        })->everySecond();

        $queue->addTasks([$task1, $task2]);

        $tasks = $queue->run();
        // Clear before asserting: run()'s tick loop always runs to its full
        // window once entered (see below), so an assertion failure here
        // must not skip cleanup and leak sub-minute tasks into later tests
        // sharing this same File-backed folder.
        $queue->clearTasks();

        $this->assertArrayHasKey($task1->getJobId(), $tasks);
        $this->assertArrayHasKey($task2->getJobId(), $tasks);
        $this->assertArrayHasKey('task1', $fireTimes);
        $this->assertArrayHasKey('task2', $fireTimes);
        // Both tasks are due immediately (everySecond()), so both should
        // fire on the shared first pass, within a fraction of a second of
        // each other - not one waiting for the other's full evaluation
        // window (the old bug's signature: task2 wouldn't fire at all
        // until task1's entire 60-second loop had finished). Note this
        // does NOT assert on run()'s total elapsed time - run()'s tick
        // loop intentionally runs to its full ~59-second window once any
        // sub-minute task is present, whether or not that task already
        // fired on tick 0 (matching the old code's behavior for a single
        // sub-minute task, and the spec's Non-goals, which keep the
        // blocking wait as-is until daemon mode exists).
        $this->assertLessThan(1.5, abs($fireTimes['task2'] - $fireTimes['task1']));
    }

    public function testRunCoarseTaskEvaluatedPromptlyNotDelayedBehindSubMinuteTask()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));

        $start = microtime(true);
        $fireTimes = [];

        $subMinuteTask = Task::create(function() use (&$fireTimes, $start){
            $fireTimes['sub'] = microtime(true) - $start;
        })->everySecond();
        $coarseTask = Task::create(function() use (&$fireTimes, $start){
            $fireTimes['coarse'] = microtime(true) - $start;
        })->everyMinute()->setBuffer(-1);

        // Sub-minute task scheduled first, so under the old bug the coarse
        // task (scheduled second) would wait behind its full 60-second loop.
        $queue->addTasks([$subMinuteTask, $coarseTask]);

        $tasks = $queue->run();
        $queue->clearTasks();

        $this->assertArrayHasKey($coarseTask->getJobId(), $tasks);
        $this->assertArrayHasKey('coarse', $fireTimes);
        // The coarse task fires on the shared first pass, before any
        // sleep() happens - well under a second, not delayed behind the
        // sub-minute task's evaluation window. (As above, this doesn't
        // assert on run()'s total elapsed time, which still runs out its
        // full tick-loop window because a sub-minute task is present.)
        $this->assertLessThan(1.0, $fireTimes['coarse']);
    }

    public function testRunReturnsPromptlyWithNoSubMinuteTasks()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $task  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute()->setBuffer(-1);

        $queue->addTask($task);

        $start = microtime(true);
        $tasks = $queue->run();
        $elapsed = microtime(true) - $start;
        $queue->clearTasks();

        $this->assertArrayHasKey($task->getJobId(), $tasks);
        // No sub-minute task exists here, so the tick loop is skipped
        // entirely - run() returns right after the single shared pass.
        $this->assertLessThan(1, $elapsed);
    }

}
