<?php

namespace Pop\Queue\Test;

use Pop\Application;
use Pop\Event\Manager as EventManager;
use Pop\Queue\Adapter\File;
use Pop\Queue\Adapter\Memory;
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

    public function testFake()
    {
        $queue = Queue::fake();
        $this->assertInstanceOf('Pop\Queue\Queue', $queue);
        $this->assertInstanceOf('Pop\Queue\Adapter\Memory', $queue->getAdapter());
        $this->assertEquals('pop-queue', $queue->getName());

        $job = Job::create(function(){
            return 123;
        });
        $queue->addJob($job);
        $job = $queue->work();

        $this->assertEquals(123, $job->getResults());
    }

    public function testFakeWithCustomNameAndPriorityAndLease()
    {
        $queue = Queue::fake('test-queue', 'FILO', 30);
        $this->assertEquals('test-queue', $queue->getName());
        $this->assertEquals('FILO', $queue->getPriority());
    }

    public function testHasNameReturnsFalseWhenNeverSet()
    {
        $queue = new class extends \Pop\Queue\AbstractQueue {
            public function work(?\Pop\Application $application = null): ?\Pop\Queue\Process\AbstractJob
            {
                return null;
            }
            public function run(?\Pop\Application $application = null): array
            {
                return [];
            }
            public function clear(): \Pop\Queue\AbstractQueue
            {
                return $this;
            }
        };

        $this->assertFalse($queue->hasName());
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

    public function testWorkExecJobFailureIsRecordedAsFailedNotCompleted()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::exec(['false']);
        $job->setMaxAttempts(1);

        $queue->addJob($job);
        $job = $queue->work();

        $this->assertTrue($job->hasFailed());
        $this->assertFalse($job->isComplete());
        $queue->clearFailed();
    }

    public function testWorkExecJobTimeoutIsEnforcedByProcessNotPcntlAlarm()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::exec(['sleep', '3']);
        $job->setTimeout(1);
        $job->setMaxAttempts(1);

        $queue->addJob($job);

        $start   = microtime(true);
        $job     = $queue->work();
        $elapsed = microtime(true) - $start;

        $this->assertTrue($job->hasFailed());
        // Proves the child was actually interrupted around the 1-second
        // configured timeout, not left to run the full 3 seconds.
        $this->assertLessThan(2.5, $elapsed);

        // Queue's own pcntl-based TimeoutException always reads
        // "... exceeded its N second timeout." - confirm that text is
        // absent, proving runWithTimeout() deferred entirely to Process's
        // own timeout for this exec job rather than layering its own
        // competing pcntl alarm on top (which could abort Process's
        // internal wait()/kill logic mid-flight and leave the child
        // process orphaned - see runWithTimeout()'s docblock).
        $messages = $job->getFailedMessages();
        $this->assertStringNotContainsString('second timeout', end($messages));

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

    public function testEvaluateTasksOnceReclaimsAfterSubMinuteFailureSoASecondWorkerCannotDoubleRun()
    {
        $adapter = new File(__DIR__ . '/tmp/pop-queue');
        $queue1  = Queue::create('pop-queue', $adapter);
        $queue2  = Queue::create('pop-queue', $adapter);

        $task = Task::create(function(){
            throw new \Exception('Error!');
        })->everySecond();

        $queue1->addTask($task);

        $method = new \ReflectionMethod($queue1, 'evaluateTasksOnce');
        $method->setAccessible(true);

        $scheduledTasks = [$task->getJobId() => $adapter->getTask($task->getJobId())];

        $ran1 = $method->invoke($queue1, $scheduledTasks, null, false);
        $ran2 = $method->invoke($queue2, $scheduledTasks, null, false);

        $ranInPass1 = array_key_exists($task->getJobId(), $ran1);
        $ranInPass2 = array_key_exists($task->getJobId(), $ran2);

        $this->assertTrue($ranInPass1 xor $ranInPass2, 'Only one of the two evaluateTasksOnce() calls should have executed the failing sub-minute task for the same window - the claim must survive the failure-handling remove+reschedule cycle.');

        $adapter->clearTasks();
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

    public function testGetScheduledTasksReturnsTaskSet()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $task  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute();

        $queue->addTask($task);

        $scheduledTasks = $queue->getScheduledTasks();
        $this->assertArrayHasKey($task->getJobId(), $scheduledTasks);
        $this->assertInstanceOf('Pop\Queue\Process\Task', $scheduledTasks[$task->getJobId()]);

        $queue->clearTasks();
    }

    public function testGetScheduledTasksReturnsEmptyArrayWhenNoTasks()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $this->assertEquals([], $queue->getScheduledTasks());
    }

    public function testEvaluateTasksOnceIsPubliclyCallable()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $task  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everySecond();

        // No reflection needed - evaluateTasksOnce() is public now, called
        // directly the way Worker::runAll() (Task 2) needs to call it
        // across multiple queues.
        $ran = $queue->evaluateTasksOnce([$task->getJobId() => $task], null, false);
        $this->assertArrayHasKey($task->getJobId(), $ran);
    }

    public function testSetAndGetEvents()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();

        $this->assertFalse($queue->hasEvents());
        $queue->setEvents($events);
        $this->assertTrue($queue->hasEvents());
        $this->assertSame($events, $queue->getEvents());
        $this->assertSame($events, $queue->events());
    }

    public function testTriggerEventUsesQueueEventsWhenSet()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $fired  = [];
        $events->on('test.event', function($foo) use (&$fired) {
            $fired[] = $foo;
        });
        $queue->setEvents($events);

        $method = new \ReflectionMethod($queue, 'triggerEvent');
        $method->setAccessible(true);
        $method->invoke($queue, 'test.event', ['foo' => 'bar']);

        $this->assertEquals(['bar'], $fired);
    }

    public function testTriggerEventFallsBackToApplicationEvents()
    {
        $queue       = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $application = new Application();
        $events      = new EventManager();
        $fired       = [];
        $events->on('test.event', function($foo) use (&$fired) {
            $fired[] = $foo;
        });
        $application->registerEvents($events);

        $method = new \ReflectionMethod($queue, 'triggerEvent');
        $method->setAccessible(true);
        $method->invoke($queue, 'test.event', ['foo' => 'bar'], $application);

        $this->assertEquals(['bar'], $fired);
    }

    public function testTriggerEventPrefersQueueEventsOverApplication()
    {
        $queue       = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $queueEvents = new EventManager();
        $appEvents   = new EventManager();
        $application = new Application();
        $queueFired  = [];
        $appFired    = [];

        $queueEvents->on('test.event', function($foo) use (&$queueFired) {
            $queueFired[] = $foo;
        });
        $appEvents->on('test.event', function($foo) use (&$appFired) {
            $appFired[] = $foo;
        });

        $queue->setEvents($queueEvents);
        $application->registerEvents($appEvents);

        $method = new \ReflectionMethod($queue, 'triggerEvent');
        $method->setAccessible(true);
        $method->invoke($queue, 'test.event', ['foo' => 'bar'], $application);

        $this->assertEquals(['bar'], $queueFired);
        $this->assertEquals([], $appFired);
    }

    public function testTriggerEventIsNoOpWithNoEventsAnywhere()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));

        $method = new \ReflectionMethod($queue, 'triggerEvent');
        $method->setAccessible(true);

        // Must not throw.
        $method->invoke($queue, 'test.event', ['foo' => 'bar']);
        $this->assertTrue(true);
    }

    public function testTriggerEventIsNoOpWhenApplicationHasNoListenersForTheEvent()
    {
        $queue       = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $application = new Application();

        $method = new \ReflectionMethod($queue, 'triggerEvent');
        $method->setAccessible(true);

        // Pop\Application::__construct() always calls bootstrap(), which
        // always registers a non-null (empty) Event\Manager if one wasn't
        // already set - so $application->events() is never null here. This
        // exercises Manager::trigger()'s own no-op for an event name with
        // no registered listeners, not the `$application->events() !== null`
        // guard inside triggerEvent() - that guard is defensive only (Queue's
        // $events property is protected and there's no public way to hand it
        // a null Application-with-null-events through this codebase's own
        // API), and is fine to leave uncovered as a result.
        $method->invoke($queue, 'test.event', ['foo' => 'bar'], $application);
        $this->assertTrue(true);
    }

    public function testWorkFiresJobPreAndPostEventsOnSuccess()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $fired  = [];

        $events->on('queue.job.pre', function($job, $queue) use (&$fired) {
            $fired[] = ['queue.job.pre', $job->getJobId()];
        });
        $events->on('queue.job.post', function($job, $queue) use (&$fired) {
            $fired[] = ['queue.job.post', $job->getJobId()];
        });
        $events->on('queue.job.failed', function() use (&$fired) {
            $fired[] = ['queue.job.failed'];
        });
        $events->on('queue.job.buried', function() use (&$fired) {
            $fired[] = ['queue.job.buried'];
        });
        $queue->setEvents($events);

        $job = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });
        $queue->addJob($job);

        $result = $queue->work();

        $this->assertEquals([
            ['queue.job.pre', $job->getJobId()],
            ['queue.job.post', $job->getJobId()],
        ], $fired);

        $queue->clear();
    }

    public function testWorkFiresJobFailedEventOnFailureStillValidForRetry()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $fired  = [];

        $events->on('queue.job.pre', function() use (&$fired) {
            $fired[] = 'pre';
        });
        $events->on('queue.job.post', function() use (&$fired) {
            $fired[] = 'post';
        });
        $events->on('queue.job.failed', function($job, $queue, $exception) use (&$fired) {
            $fired[] = 'failed:' . $exception->getMessage();
        });
        $events->on('queue.job.buried', function() use (&$fired) {
            $fired[] = 'buried';
        });
        $queue->setEvents($events);

        $job = Job::create(function(){
            throw new \Exception('Boom!');
        });
        $queue->addJob($job);

        $queue->work();

        $this->assertEquals(['pre', 'failed:Boom!'], $fired);

        $queue->clear();
        $queue->clearFailed();
    }

    public function testWorkFiresJobFailedAndBuriedEventsWhenNoLongerValid()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $fired  = [];

        $events->on('queue.job.failed', function($job, $queue, $exception) use (&$fired) {
            $fired[] = 'failed';
        });
        $events->on('queue.job.buried', function($job, $queue, $reason) use (&$fired) {
            $fired[] = 'buried:' . $reason;
        });
        $queue->setEvents($events);

        $job = Job::create(function(){
            throw new \Exception('Boom!');
        });
        $job->setMaxAttempts(1);
        $queue->addJob($job);

        $queue->work();

        $this->assertEquals(['failed', 'buried:Boom!'], $fired);

        $queue->clear();
        $queue->clearFailed();
    }

    public function testWorkFiresOnlyBuriedEventWhenJobIsAlreadyInvalidBeforeRunning()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $fired  = [];

        $events->on('queue.job.pre', function() use (&$fired) {
            $fired[] = 'pre';
        });
        $events->on('queue.job.buried', function($job, $queue, $reason) use (&$fired) {
            $fired[] = 'buried:' . $reason;
        });
        $queue->setEvents($events);

        $job = Job::create(function(){
            return 'never runs';
        });
        // runUntil() in the past makes isExpired() (and so isValid()) false
        // before the job is ever reserved/run - AbstractJob has no public
        // attempts setter, so an already-past runUntil is the way to
        // construct an already-invalid job without running it first.
        $job->runUntil(time() - 10);
        $queue->addJob($job);

        $queue->work();

        $this->assertEquals(['buried:Exceeded max attempts or expired before execution'], $fired);

        $queue->clear();
        $queue->clearFailed();
    }

    public function testWorkListenerExceptionPropagatesAndIsNotMisattributedAsJobFailure()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();

        $events->on('queue.job.post', function() {
            throw new \RuntimeException('Listener bug!');
        });
        $queue->setEvents($events);

        $job = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });
        $queue->addJob($job);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Listener bug!');

        try {
            $queue->work();
        } finally {
            // The job's own execution succeeded and was already delete()'d
            // as completed - by the time the queue.job.post listener threw,
            // the try/catch had already fully resolved, so it must not have
            // been released back to pending or buried as if IT had failed.
            // AdapterInterface has no per-job "was this buried/dead" lookup
            // by ID other than getDeadJob(), so confirm via both: nothing
            // active left (the completed job was deleted) and nothing dead.
            $this->assertFalse($queue->adapter()->hasJobs());
            $this->assertFalse($queue->adapter()->hasDeadJobs());
        }
    }

    public function testWorkWithNoEventsSetIsUnaffected()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });
        $queue->addJob($job);

        $result = $queue->work();
        $this->assertTrue($result->isComplete());

        $queue->clear();
    }

    public function testEvaluateTasksOnceFiresTaskPreAndPostEventsOnSuccess()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $fired  = [];

        $events->on('queue.task.pre', function($task) use (&$fired) {
            $fired[] = ['queue.task.pre', $task->getJobId()];
        });
        $events->on('queue.task.post', function($task) use (&$fired) {
            $fired[] = ['queue.task.post', $task->getJobId()];
        });
        $events->on('queue.task.failed', function() use (&$fired) {
            $fired[] = ['queue.task.failed'];
        });
        $queue->setEvents($events);

        $task = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everySecond();

        $ran = $queue->evaluateTasksOnce([$task->getJobId() => $task], null, false);

        $this->assertEquals([
            ['queue.task.pre', $task->getJobId()],
            ['queue.task.post', $task->getJobId()],
        ], $fired);
        $this->assertArrayHasKey($task->getJobId(), $ran);
    }

    public function testWorkForwardsApplicationToTriggerEventEndToEnd()
    {
        $queue       = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $application = new Application();
        $events      = new EventManager();
        $fired       = [];

        $events->on('queue.job.post', function($job, $queue) use (&$fired) {
            $fired[] = $job->getJobId();
        });
        $application->registerEvents($events);

        $job = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });
        $queue->addJob($job);

        $queue->work($application);

        $this->assertEquals([$job->getJobId()], $fired);

        $queue->clear();
    }

    public function testEvaluateTasksOnceFiresTaskFailedEvent()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $fired  = [];

        $events->on('queue.task.pre', function() use (&$fired) {
            $fired[] = 'pre';
        });
        $events->on('queue.task.post', function() use (&$fired) {
            $fired[] = 'post';
        });
        $events->on('queue.task.failed', function($task, $queue, $exception) use (&$fired) {
            $fired[] = 'failed:' . $exception->getMessage();
        });
        $queue->setEvents($events);

        $task = Task::create(function(){
            throw new \Exception('Task boom!');
        })->everySecond();

        $queue->evaluateTasksOnce([$task->getJobId() => $task], null, false);

        $this->assertEquals(['pre', 'failed:Task boom!'], $fired);
    }

}
