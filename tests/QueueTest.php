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

    public function testRunCoSchedulesBothSubMinuteTasksOnEveryTick()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));

        $task1 = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everySecond();
        $task2 = Task::create(function(){
            return 'Task #2' . PHP_EOL;
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

        // Read timing off the Task objects run() actually executed and
        // returned (AbstractJob::run() calls start(), which stamps
        // getStarted() with time()) rather than a closure side-effect: a
        // task closure captured `use (&$var)` looks fine in-memory, but
        // Queue::run() round-trips every task through the adapter's
        // serialize/store/reload/__wakeup() cycle before invoking it, and
        // PHP closure serialization cannot preserve a by-reference capture
        // across that round trip - the closure that actually runs is a
        // deserialized copy, so writes through the reference never reach
        // the original test-local variable. Reading getStarted() off the
        // returned Task avoids that pitfall entirely.
        $started1 = $tasks[$task1->getJobId()]->getStarted();
        $started2 = $tasks[$task2->getJobId()]->getStarted();

        $this->assertNotNull($started1);
        $this->assertNotNull($started2);
        // Both tasks are due immediately (everySecond()), so both should
        // stay co-scheduled within about a second of each other on every
        // tick - not one waiting for the other's full evaluation window
        // (the old bug's signature: task2 wouldn't fire at all until
        // task1's entire 60-second loop had finished, ~59-60s apart, not
        // ~0-1s). Note this does NOT assert on run()'s total elapsed time
        // - run()'s tick loop intentionally runs to its full ~59-second
        // window once any sub-minute task is present, whether or not that
        // task already fired on tick 0 (matching the old code's behavior
        // for a single sub-minute task, and the spec's Non-goals, which
        // keep the blocking wait as-is until daemon mode exists).
        $this->assertLessThanOrEqual(1, abs($started2 - $started1));
    }

    public function testRunCoarseTaskEvaluatedPromptlyNotDelayedBehindSubMinuteTask()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));

        $subMinuteTask = Task::create(function(){
            return 'sub-minute' . PHP_EOL;
        })->everySecond();
        $coarseTask = Task::create(function(){
            return 'coarse' . PHP_EOL;
        })->everyMinute()->setBuffer(-1);

        $start = time();

        // Sub-minute task scheduled first, so under the old bug the coarse
        // task (scheduled second) would wait behind its full 60-second loop.
        $queue->addTasks([$subMinuteTask, $coarseTask]);

        $tasks = $queue->run();
        $queue->clearTasks();

        $this->assertArrayHasKey($coarseTask->getJobId(), $tasks);

        // See the comment in the previous test for why this reads
        // getStarted() off the returned Task rather than a closure
        // side-effect.
        $coarseStarted = $tasks[$coarseTask->getJobId()]->getStarted();
        $this->assertNotNull($coarseStarted);
        // The coarse task fires on the shared first pass, before any
        // sleep() happens - within a few seconds of run() starting, not
        // delayed ~59-60s behind the sub-minute task's evaluation window.
        // (As above, this doesn't assert on run()'s total elapsed time,
        // which still runs out its full tick-loop window because a
        // sub-minute task is present.)
        $this->assertLessThanOrEqual(5, $coarseStarted - $start);
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
        $this->assertLessThan(5, $elapsed);
    }

    public function testRunOnlyOneOfTwoQueuesSharingAnAdapterExecutesTheSameDueTask()
    {
        $adapter = new File(__DIR__ . '/tmp/pop-queue');
        $queue1  = Queue::create('pop-queue', $adapter);
        $queue2  = Queue::create('pop-queue', $adapter);

        $task = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute()->setBuffer(-1);

        $queue1->addTask($task);

        // Two Queue instances sharing one adapter instance/folder,
        // evaluating the same due task in the same process - proves the
        // adapter-level claim actually gates Queue::run(), without needing
        // a real second process (the adapter-level tests already prove
        // real cross-process concurrency).
        $tasks1 = $queue1->run();
        $tasks2 = $queue2->run();

        $ranInQueue1 = array_key_exists($task->getJobId(), $tasks1);
        $ranInQueue2 = array_key_exists($task->getJobId(), $tasks2);

        $this->assertTrue($ranInQueue1 xor $ranInQueue2, 'Exactly one of the two queues should have executed the shared due task.');

        $queue1->clearTasks();
    }

}
