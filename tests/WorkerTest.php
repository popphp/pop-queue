<?php

namespace Pop\Queue\Test;

use Pop\Application;
use Pop\Event\Manager as EventManager;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
use Pop\Queue\Worker;
use Pop\Queue\Queue;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{

    /**
     * Set from within a job closure in
     * testStopCalledFromWithinAJobDoesNotInterruptThatJob() - a static
     * property because the closure that sets it runs as a deserialized
     * clone by the time it actually executes (see that test's comments
     * for why), so a captured "use (&$var)" reference wouldn't survive
     * the round-trip, but a static property (owned by the class, not any
     * one instance) does.
     * @var bool
     */
    protected static bool $ranAfterStop = false;

    public function testConstructor()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue, new Application());
        $this->assertInstanceOf('Pop\Queue\Worker', $worker);
        $this->assertTrue($worker->hasQueue('pop-queue'));
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->getQueue('pop-queue'));
        $this->assertCount(1, $worker->getQueues());
        $this->assertTrue($worker->hasApplication());
        $this->assertInstanceOf('Pop\Application', $worker->getApplication());
        $this->assertInstanceOf('Pop\Application', $worker->application());
    }

    public function testAddQueues()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create([$queue1, $queue2]);
        $this->assertTrue($worker->hasQueue('pop-queue1'));
        $this->assertTrue($worker->hasQueue('pop-queue2'));
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->getQueue('pop-queue1'));
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->getQueue('pop-queue1'));
        $this->assertCount(2, $worker->getQueues());
        $this->assertEquals(2, $worker->count());
    }

    public function testMagicMethods()
    {
        $queue1 = Queue::create('pop_queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop_queue2', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create();
        $worker['pop_queue1'] = $queue1;
        $worker->pop_queue2   = $queue2;
        $this->assertCount(2, $worker->getQueues());

        $this->assertInstanceOf('Pop\Queue\Queue', $worker['pop_queue1']);
        $this->assertInstanceOf('Pop\Queue\Queue', $worker['pop_queue2']);
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->pop_queue1);
        $this->assertInstanceOf('Pop\Queue\Queue', $worker->pop_queue2);
        $this->assertTrue(isset($worker->pop_queue1));
        $this->assertTrue(isset($worker->pop_queue2));
        $this->assertTrue(isset($worker['pop_queue1']));
        $this->assertTrue(isset($worker['pop_queue2']));
        unset($worker->pop_queue1);
        unset($worker['pop_queue2']);
        $this->assertFalse(isset($worker['pop_queue1']));
        $this->assertFalse(isset($worker['pop_queue2']));
    }

    public function testIterator()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create([$queue1, $queue2]);
        $count = 0;
        foreach ($worker as $queue) {
            if ($queue instanceof Queue) {
                $count++;
            }
        }
        $this->assertEquals(2, $count);
    }

    public function testWorkQueue()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });

        $queue->addJob($job);

        $worker = Worker::create($queue);
        $job = $worker->work('pop-queue');
        $this->assertTrue($job->isComplete());
        $this->assertTrue(is_numeric($job->getCompleted()));
        $worker->clear('pop-queue');
    }


    public function testWorkQueueFailed()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            throw new \Exception('Error!');
        });

        $queue->addJob($job);

        $worker = Worker::create($queue);
        $job = $worker->work('pop-queue');
        $this->assertTrue($job->hasFailed());
        $this->assertTrue($job->hasFailedMessages());
        $this->assertEquals('Error!', $job->getFailedMessages()[$job->getFailed()]);
        $this->assertEquals(1, $job->getAttempts());
        $this->assertTrue($queue->adapter()->hasJobs());
        $worker->clear('pop-queue');
    }

    public function testWorkQueues()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue2'));
        $job1   = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });
        $job2   = Job::create(function(){
            return 'Job #2' . PHP_EOL;
        });

        $queue1->addJob($job1);
        $queue2->addJob($job2);

        $worker = Worker::create([$queue1, $queue2]);
        $jobs = $worker->workAll();
        $this->assertCount(2, $jobs);
        $worker->clearAll();
    }

    public function testWorkQueuesFailed()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue2'));
        $job1   = Job::create(function(){
            throw new \Exception('Error!');
        });
        $job2   = Job::create(function(){
            throw new \Exception('Error!');
        });
        $job1->setMaxAttempts(1);
        $job2->setMaxAttempts(1);

        $queue1->addJob($job1);
        $queue2->addJob($job2);

        $worker = Worker::create([$queue1, $queue2]);
        $jobs = $worker->workAll();

        $this->assertTrue($queue1->adapter()->hasDeadJobs());
        $this->assertTrue($queue2->adapter()->hasDeadJobs());
        $worker->clearAllFailed();
        $this->assertFalse($queue1->adapter()->hasDeadJobs());
        $this->assertFalse($queue2->adapter()->hasDeadJobs());
        $worker->clearAll();
    }

    public function testRunQueue()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $task  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute();

        $queue->addTask($task);

        $worker = Worker::create($queue);
        $tasks = $worker->run('pop-queue');
        $this->assertTrue(is_array($tasks));
        $this->assertTrue($queue->adapter()->hasTasks());
        $worker->clearTasks('pop-queue');
        $this->assertFalse($queue->adapter()->hasTasks());
        $worker->clear('pop-queue');
    }

    public function testRunQueues()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue2'));
        $task1  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute();
        $task2  = Task::create(function(){
            return 'Task #2' . PHP_EOL;
        })->every5Minutes();

        $queue1->addTask($task1);
        $queue2->addTask($task2);

        $worker = Worker::create([$queue1, $queue2]);
        $tasks = $worker->runAll();
        $this->assertTrue(is_array($tasks));
        $this->assertTrue($queue1->adapter()->hasTasks());
        $this->assertTrue($queue2->adapter()->hasTasks());
        $worker->clearAllTasks();
        $this->assertFalse($queue1->adapter()->hasTasks());
        $this->assertFalse($queue2->adapter()->hasTasks());
        $worker->clearAll();
    }

    public function testGetQueuesOrdersByWeightDescendingWithInsertionOrderTiebreak()
    {
        $queueLow    = Queue::create('low', new File(__DIR__ . '/tmp/pop-queue'));
        $queueHigh   = Queue::create('high', new File(__DIR__ . '/tmp/pop-queue'));
        $queueMid    = Queue::create('mid', new File(__DIR__ . '/tmp/pop-queue'));
        $queueMidToo = Queue::create('mid-too', new File(__DIR__ . '/tmp/pop-queue'));

        $worker = Worker::create();
        $worker->addQueue($queueLow, 1);
        $worker->addQueue($queueHigh, 10);
        $worker->addQueue($queueMid, 5);
        $worker->addQueue($queueMidToo, 5);

        $names = array_keys($worker->getQueues());
        $this->assertEquals(['high', 'mid', 'mid-too', 'low'], $names);
    }

    public function testAddQueueDefaultsToZeroWeightPreservingInsertionOrder()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create([$queue1, $queue2]);

        $this->assertEquals(['pop-queue1', 'pop-queue2'], array_keys($worker->getQueues()));
        $this->assertEquals(0, $worker->getWeight('pop-queue1'));
        $this->assertEquals(0, $worker->getWeight('pop-queue2'));
    }

    public function testUnsetQueueAlsoClearsItsWeight()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create();
        $worker->addQueue($queue, 5);
        $this->assertEquals(5, $worker->getWeight('pop-queue'));

        unset($worker['pop-queue']);

        $this->assertFalse($worker->hasQueue('pop-queue'));
        $this->assertEquals(0, $worker->getWeight('pop-queue'));
    }

    public function testWorkAllIteratesInWeightOrder()
    {
        $queueLow  = Queue::create('low', new File(__DIR__ . '/tmp/pop-queue'));
        $queueHigh = Queue::create('high', new File(__DIR__ . '/tmp/pop-queue'));

        $worker = Worker::create();
        $worker->addQueue($queueLow, 1);
        $worker->addQueue($queueHigh, 10);

        $jobs = $worker->workAll();
        $this->assertEquals(['high', 'low'], array_keys($jobs));
    }

    public function testIteratorRespectsWeightOrder()
    {
        $queueLow  = Queue::create('low', new File(__DIR__ . '/tmp/pop-queue'));
        $queueHigh = Queue::create('high', new File(__DIR__ . '/tmp/pop-queue'));

        $worker = Worker::create();
        $worker->addQueue($queueLow, 1);
        $worker->addQueue($queueHigh, 10);

        $names = [];
        foreach ($worker as $name => $queue) {
            $names[] = $name;
        }
        $this->assertEquals(['high', 'low'], $names);
    }

    public function testWorkWithExplicitQueueNameBehaviorUnchanged()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $job   = Job::create(function(){
            return 'Job #1' . PHP_EOL;
        });
        $queue->addJob($job);

        $worker = Worker::create($queue);
        $result = $worker->work('pop-queue');
        $this->assertTrue($result->isComplete());

        $worker->clear('pop-queue');
    }

    public function testWorkWithNoQueueNameServicesHighestWeightNonEmptyQueueFirst()
    {
        $queueLow  = Queue::create('low', new File(__DIR__ . '/tmp/pop-queue-low'));
        $queueHigh = Queue::create('high', new File(__DIR__ . '/tmp/pop-queue-high'));

        $lowJob  = Job::create(function(){ return 'low job'; });
        $highJob = Job::create(function(){ return 'high job'; });
        $queueLow->addJob($lowJob);
        $queueHigh->addJob($highJob);

        $worker = Worker::create();
        $worker->addQueue($queueLow, 1);
        $worker->addQueue($queueHigh, 10);

        $job = $worker->work();
        $this->assertEquals($highJob->getJobId(), $job->getJobId());

        $worker->clear('low');
        $worker->clear('high');
    }

    public function testWorkWithNoQueueNameFallsThroughToLowerWeightWhenHigherIsEmpty()
    {
        $queueLow  = Queue::create('low', new File(__DIR__ . '/tmp/pop-queue-low'));
        $queueHigh = Queue::create('high', new File(__DIR__ . '/tmp/pop-queue-high'));

        $lowJob = Job::create(function(){ return 'low job'; });
        $queueLow->addJob($lowJob);

        $worker = Worker::create();
        $worker->addQueue($queueLow, 1);
        $worker->addQueue($queueHigh, 10);

        $job = $worker->work();
        $this->assertEquals($lowJob->getJobId(), $job->getJobId());

        $worker->clear('low');
        $worker->clear('high');
    }

    public function testWorkWithNoQueueNameReturnsNullWhenEveryQueueIsEmpty()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue);

        $this->assertNull($worker->work());
    }

    public function testRunAllIncludesEveryQueueEvenWithNoTasksDue()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue2'));

        $worker = Worker::create([$queue1, $queue2]);
        $tasks  = $worker->runAll();

        $this->assertArrayHasKey('pop-queue1', $tasks);
        $this->assertArrayHasKey('pop-queue2', $tasks);
        $this->assertEquals([], $tasks['pop-queue1']);
        $this->assertEquals([], $tasks['pop-queue2']);
    }

    public function testRunAllEvaluatesBothQueuesSubMinuteTasksFairly()
    {
        $queue1 = Queue::create('pop-queue1', new File(__DIR__ . '/tmp/pop-queue'));
        $queue2 = Queue::create('pop-queue2', new File(__DIR__ . '/tmp/pop-queue2'));

        $task1 = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everySecond();
        $task2 = Task::create(function(){
            return 'Task #2' . PHP_EOL;
        })->everySecond();

        $queue1->addTask($task1);
        $queue2->addTask($task2);

        $worker = Worker::create([$queue1, $queue2]);
        $start   = microtime(true);
        $tasks   = $worker->runAll();
        $elapsed = microtime(true) - $start;

        $this->assertArrayHasKey($task1->getJobId(), $tasks['pop-queue1']);
        $this->assertArrayHasKey($task2->getJobId(), $tasks['pop-queue2']);

        $started1 = $tasks['pop-queue1'][$task1->getJobId()]->getStarted();
        $started2 = $tasks['pop-queue2'][$task2->getJobId()]->getStarted();

        $this->assertNotNull($started1);
        $this->assertNotNull($started2);
        // Both queues' tasks are due immediately, so both should fire
        // within the same second or the next - not one queue waiting
        // behind the other's full ~59-second tick loop (the old bug's
        // signature: queue2 wouldn't be touched at all until queue1's
        // run() had fully returned).
        $this->assertLessThanOrEqual(1, abs($started2 - $started1));

        // A shared tick loop (sleep(1) once per tick, not once per queue per
        // tick) means two queues each with a sub-minute task still finish in
        // ~59 seconds total, not ~118s - this is the property a regression that
        // moved sleep() inside the per-queue loop would silently break, even
        // though it would NOT break the getStarted()-gap assertion above (both
        // tasks would still fire together on the very first pass, before any
        // sleep happens either way).
        $this->assertLessThan(90, $elapsed);

        $worker->clearAllTasks();
    }

    public function testSetAndGetEventsOnWorker()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue);
        $events = new EventManager();

        $this->assertFalse($worker->hasEvents());
        $worker->setEvents($events);
        $this->assertTrue($worker->hasEvents());
        $this->assertSame($events, $worker->getEvents());
        $this->assertSame($events, $worker->events());
    }

    public function testTriggerEventOnWorkerUsesWorkerEventsWhenSet()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue);
        $events = new EventManager();
        $fired  = [];
        $events->on('test.event', function($foo) use (&$fired) {
            $fired[] = $foo;
        });
        $worker->setEvents($events);

        $method = new \ReflectionMethod($worker, 'triggerEvent');
        $method->setAccessible(true);
        $method->invoke($worker, 'test.event', ['foo' => 'bar']);

        $this->assertEquals(['bar'], $fired);
    }

    public function testTriggerEventOnWorkerFallsBackToApplicationEvents()
    {
        $queue       = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $application = new Application();
        $events      = new EventManager();
        $fired       = [];
        $events->on('test.event', function($foo) use (&$fired) {
            $fired[] = $foo;
        });
        $application->registerEvents($events);

        $worker = Worker::create($queue, $application);

        $method = new \ReflectionMethod($worker, 'triggerEvent');
        $method->setAccessible(true);
        $method->invoke($worker, 'test.event', ['foo' => 'bar']);

        $this->assertEquals(['bar'], $fired);
    }

    public function testTriggerEventOnWorkerPrefersWorkerEventsOverApplication()
    {
        $queue        = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $workerEvents = new EventManager();
        $appEvents    = new EventManager();
        $application  = new Application();
        $workerFired  = [];
        $appFired     = [];

        $workerEvents->on('test.event', function($foo) use (&$workerFired) {
            $workerFired[] = $foo;
        });
        $appEvents->on('test.event', function($foo) use (&$appFired) {
            $appFired[] = $foo;
        });

        $worker = Worker::create($queue, $application);
        $worker->setEvents($workerEvents);
        $application->registerEvents($appEvents);

        $method = new \ReflectionMethod($worker, 'triggerEvent');
        $method->setAccessible(true);
        $method->invoke($worker, 'test.event', ['foo' => 'bar']);

        $this->assertEquals(['bar'], $workerFired);
        $this->assertEquals([], $appFired);
    }

    public function testTriggerEventOnWorkerIsNoOpWithNoEventsAnywhere()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue);

        $method = new \ReflectionMethod($worker, 'triggerEvent');
        $method->setAccessible(true);

        // Must not throw.
        $method->invoke($worker, 'test.event', ['foo' => 'bar']);
        $this->assertTrue(true);
    }

    public function testStopSetsStoppedFlag()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue);

        $this->assertFalse($worker->isStopped());
        $worker->stop();
        $this->assertTrue($worker->isStopped());
    }

    public function testWorkLoopSleepsWhenIdleAndStopsViaListener()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $ticks  = 0;

        $worker = Worker::create($queue);
        $worker->setEvents($events);

        $events->on('worker.work_loop.idle', function($worker) use (&$ticks) {
            $ticks++;
            if ($ticks >= 2) {
                $worker->stop();
            }
        });

        $start = microtime(true);
        $worker->workLoop(1);
        $elapsed = microtime(true) - $start;

        // Two idle ticks means at least 2 real one-second sleeps happened
        // before stop() was called - proving the backoff genuinely sleeps,
        // not just that the loop eventually returns.
        $this->assertGreaterThanOrEqual(2, $elapsed);
        $this->assertEquals(2, $ticks);
    }

    public function testWorkLoopDrainsAvailableJobsWithoutSleepingBetweenThem()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        for ($i = 0; $i < 3; $i++) {
            $queue->addJob(Job::create(function(){
                return 'job';
            }));
        }

        $worker = Worker::create($queue);
        $drained = 0;

        $events = new EventManager();
        $events->on('worker.work_loop.tick', function($jobs, $worker) use (&$drained) {
            foreach ($jobs as $job) {
                if ($job !== null) {
                    $drained++;
                }
            }
            if ($drained >= 3) {
                $worker->stop();
            }
        });
        $worker->setEvents($events);

        $start = microtime(true);
        $worker->workLoop(5);
        $elapsed = microtime(true) - $start;

        // 3 jobs drained across 3 ticks with no idle sleep between them -
        // if the loop slept 5s between ticks regardless of whether work
        // was found, this would take >= 10s. It shouldn't take anywhere
        // close to that.
        $this->assertEquals(3, $drained);
        $this->assertLessThan(5, $elapsed);

        $worker->clear('pop-queue');
    }

    public function testStopCalledFromWithinAJobDoesNotInterruptThatJob()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        self::$ranAfterStop = false;

        $worker = Worker::create($queue);

        // Deviation from the brief's literal test code, documented here and
        // in the task report: jobs pushed through the File adapter always
        // round-trip through serialize()/unserialize() when reserved for
        // execution (Job::__sleep()/__wakeup(), and see File::push()'s own
        // "identity survives the serialize/unserialize round-trip" comment)
        // - even within the same process. That means by the time this
        // closure actually runs, $worker is a deserialized clone, not the
        // same PHP instance the test holds, and a captured "use (&$var)"
        // reference can't survive that round-trip either. Verbatim, the
        // brief's test would silently observe a worker/flag that were never
        // touched and hang forever (nothing would ever satisfy the real
        // workLoop()'s stop condition). self::$ranAfterStop sidesteps the
        // reference problem (a static property belongs to the class, not to
        // any one instance), and the tick listener below - running
        // in-process against the real $worker, not through job
        // serialization - is what actually ends the loop. Consequently, the
        // assertTrue($worker->isStopped()) assertion below is satisfied by
        // the tick listener's own stop() call, not by the job body's
        // internal $worker->stop() call (which operates on a serialized
        // clone and so can't affect the real $worker at all) - it doesn't
        // independently corroborate anything about the job itself. The
        // load-bearing assertion for "code after stop() inside a job still
        // runs" is assertTrue(self::$ranAfterStop) alone.
        $job = Job::create(function() use ($worker) {
            $worker->stop();
            // If stop() somehow tore down execution instead of just
            // setting a flag for the loop to notice later, this line
            // would never run.
            self::$ranAfterStop = true;
        });
        $queue->addJob($job);

        $events = new EventManager();
        $events->on('worker.work_loop.tick', function() use ($worker) {
            $worker->stop();
        });
        $worker->setEvents($events);

        $worker->workLoop(1);

        $this->assertTrue(self::$ranAfterStop);
        $this->assertTrue($worker->isStopped());

        $worker->clear('pop-queue');
    }

    public function testRunLoopSleepsWhenIdleAndStopsViaListener()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $ticks  = 0;

        $worker = Worker::create($queue);
        $worker->setEvents($events);

        $events->on('worker.run_loop.idle', function($worker) use (&$ticks) {
            $ticks++;
            if ($ticks >= 2) {
                $worker->stop();
            }
        });

        $start = microtime(true);
        $worker->runLoop(1);
        $elapsed = microtime(true) - $start;

        $this->assertGreaterThanOrEqual(2, $elapsed);
        $this->assertEquals(2, $ticks);
    }

    public function testRunLoopFiresTickAndStopsAfterTaskRuns()
    {
        $queue = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $task  = Task::create(function(){
            return 'Task #1' . PHP_EOL;
        })->everyMinute()->setBuffer(-1);
        $queue->addTask($task);

        $worker = Worker::create($queue);
        $events = new EventManager();
        $ranAny = false;

        $shutdownFired = false;
        $events->on('worker.run_loop.tick', function($tasks, $worker) use (&$ranAny) {
            foreach ($tasks as $queueTasks) {
                if (!empty($queueTasks)) {
                    $ranAny = true;
                }
            }
            $worker->stop();
        });
        $events->on('worker.run_loop.shutdown', function() use (&$shutdownFired) {
            $shutdownFired = true;
        });
        $worker->setEvents($events);

        $worker->runLoop(1);

        $this->assertTrue($ranAny);
        // Also covers worker.run_loop.shutdown: this test already drives
        // runLoop() to a stop via the tick listener above, so asserting the
        // shutdown event fired here costs one assertion with no added
        // runtime, rather than a separate test.
        $this->assertTrue($shutdownFired);

        $worker->clearTasks('pop-queue');
    }

    public function testWorkLoopFiresShutdownEvent()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue);
        $events = new EventManager();
        $fired  = [];

        $events->on('worker.work_loop.tick', function() use ($worker) {
            $worker->stop();
        });
        $events->on('worker.work_loop.shutdown', function() use (&$fired) {
            $fired[] = 'work_loop.shutdown';
        });
        $worker->setEvents($events);

        $worker->workLoop(1);

        $this->assertEquals(['work_loop.shutdown'], $fired);
    }

    public function testWorkLoopStillFunctionsWithoutPcntl()
    {
        // installSignalHandlers() must be a no-op when ext-pcntl isn't
        // loaded, but the loop itself (and stop()) must still work
        // correctly regardless - this test passes in either environment,
        // since it never relies on pcntl being present or absent, only on
        // stop() (a signal-independent mechanism) working.
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $worker = Worker::create($queue);

        $events = new EventManager();
        $events->on('worker.work_loop.tick', function() use ($worker) {
            $worker->stop();
        });
        $worker->setEvents($events);

        $worker->workLoop(1);

        $this->assertTrue($worker->isStopped());
    }

    public function testWorkLoopClampsNegativeSleepSecondsToZero()
    {
        // sleep(-1) throws an uncaught ValueError in PHP 8.4, but only at
        // the first idle iteration. A negative $sleepSeconds must be
        // silently clamped to 0 rather than passed straight to sleep().
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $ticks  = 0;

        $worker = Worker::create($queue);
        $worker->setEvents($events);

        $events->on('worker.work_loop.idle', function($worker) use (&$ticks) {
            $ticks++;
            if ($ticks >= 1) {
                $worker->stop();
            }
        });

        $worker->workLoop(-5);

        $this->assertEquals(1, $ticks);
        $this->assertTrue($worker->isStopped());
    }

    public function testRunLoopClampsNegativeSleepSecondsToZero()
    {
        $queue  = Queue::create('pop-queue', new File(__DIR__ . '/tmp/pop-queue'));
        $events = new EventManager();
        $ticks  = 0;

        $worker = Worker::create($queue);
        $worker->setEvents($events);

        $events->on('worker.run_loop.idle', function($worker) use (&$ticks) {
            $ticks++;
            if ($ticks >= 1) {
                $worker->stop();
            }
        });

        $worker->runLoop(-5);

        $this->assertEquals(1, $ticks);
        $this->assertTrue($worker->isStopped());
    }

}