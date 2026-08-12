<?php

namespace Pop\Queue\Test\Registry;

use Pop\Application;
use Pop\Event\Manager as EventManager;
use Pop\Queue\Queue;
use Pop\Queue\Registry\Adapter\Memory as RegistryMemory;
use Pop\Queue\Registry\WorkerRecord;
use Pop\Queue\Registry\WorkerRegistry;
use Pop\Queue\Process\Job;
use Pop\Queue\Worker;
use PHPUnit\Framework\TestCase;

class WorkerRegistryAttachTest extends TestCase
{

    public function testAttachesToTheQueuesOwnEventManagerWhenItHasOne()
    {
        $queue  = Queue::fake('billing');
        $events = new EventManager();

        $fired = [];
        $events->on('queue.job.post', function($job, $q) use (&$fired) {
            $fired[] = 'pre-existing';
        });
        $queue->setEvents($events);

        $worker   = Worker::create($queue);
        $registry = new WorkerRegistry(new RegistryMemory());
        $registry->register('w', ['billing'], WorkerRecord::MODE_DAEMON);
        $registry->attachTo($worker);

        $queue->addJob(Job::create(function(){ return 1; }));
        $queue->work();

        // The user's own listener must still fire - attaching must not
        // replace or orphan it.
        $this->assertEquals(['pre-existing'], $fired);
        // And the registry must have counted the job.
        $this->assertEquals(1, $registry->getRecord()->getJobsProcessed());
    }

    public function testAttachesToTheApplicationsManagerWhenTheQueueHasNone()
    {
        // A bare Application already carries an event manager (its
        // bootstrap creates one), so no wiring is needed here - verified
        // empirically against this framework version.
        $application = new Application();
        $this->assertNotNull($application->events());

        $queue  = Queue::fake('billing');
        $worker = Worker::create($queue, $application);

        $registry = new WorkerRegistry(new RegistryMemory());
        $registry->register('w', ['billing'], WorkerRecord::MODE_DAEMON);
        $registry->attachTo($worker);

        // The Queue must NOT have had a manager installed on it, because
        // that would suppress the Application fallback for every other
        // listener the app has registered.
        $this->assertFalse($queue->hasEvents());

        $queue->addJob(Job::create(function(){ return 1; }));
        $worker->work('billing');

        $this->assertEquals(1, $registry->getRecord()->getJobsProcessed());
    }

    public function testCreatesAManagerOnlyWhenNeitherExists()
    {
        $queue  = Queue::fake('billing');
        $worker = Worker::create($queue);

        $this->assertFalse($queue->hasEvents());

        $registry = new WorkerRegistry(new RegistryMemory());
        $registry->register('w', ['billing'], WorkerRecord::MODE_DAEMON);
        $registry->attachTo($worker);

        $this->assertTrue($queue->hasEvents());
    }

    public function testCurrentJobIsRecordedBeforeExecutionAndClearedAfter()
    {
        $queue    = Queue::fake('billing');
        $worker   = Worker::create($queue);
        $backend  = new RegistryMemory();
        $registry = new WorkerRegistry($backend);
        $record   = $registry->register('w', ['billing'], WorkerRecord::MODE_DAEMON);
        $registry->attachTo($worker);

        // The job asserts, from inside its own execution, that the registry
        // already persisted what it is working on - which is the whole point
        // of writing the current job BEFORE running it.
        $observed = null;
        $queue->addJob(Job::create(function() use ($backend, $record, &$observed) {
            $stored   = $backend->read($record->getId());
            $observed = $stored->getCurrentJobId();
            return 1;
        }));

        $queue->work();

        $this->assertNotNull($observed, 'the current job should have been persisted before execution');
        // After the job, the in-memory record is cleared (flushed on next heartbeat).
        $this->assertNull($registry->getRecord()->getCurrentJobId());
        $this->assertEquals(1, $registry->getRecord()->getJobsProcessed());
        $this->assertEquals(0, $registry->getRecord()->getJobsFailed());
    }

    public function testFailedJobIncrementsTheFailedCounter()
    {
        $queue    = Queue::fake('billing');
        $worker   = Worker::create($queue);
        $registry = new WorkerRegistry(new RegistryMemory());
        $registry->register('w', ['billing'], WorkerRecord::MODE_DAEMON);
        $registry->attachTo($worker);

        $job = Job::create(function(){ throw new \RuntimeException('boom'); });
        $job->setMaxAttempts(1);
        $queue->addJob($job);
        $queue->work();

        $this->assertEquals(0, $registry->getRecord()->getJobsProcessed());
        $this->assertEquals(1, $registry->getRecord()->getJobsFailed());
        $this->assertNull($registry->getRecord()->getCurrentJobId());
    }

    public function testAttachIsSafeWhenNotRegistered()
    {
        $queue    = Queue::fake('billing');
        $worker   = Worker::create($queue);
        $registry = new WorkerRegistry(new RegistryMemory());

        // Never registered - listeners must no-op rather than fatal on a
        // null record.
        $registry->attachTo($worker);

        $queue->addJob(Job::create(function(){ return 1; }));
        $queue->work();

        $this->assertFalse($registry->isRegistered());
    }

    public function testCountersAreNotInflatedWhenSeveralQueuesShareOneEventManager()
    {
        $application = new Application();
        $q1 = Queue::fake('alpha');
        $q2 = Queue::fake('beta');
        $worker = Worker::create([$q1, $q2], $application);

        $registry = new WorkerRegistry(new RegistryMemory());
        $registry->register('w', ['alpha', 'beta'], WorkerRecord::MODE_DAEMON);
        $registry->attachTo($worker);

        $q1->addJob(Job::create(function(){ return 1; }));
        $worker->work('alpha');

        // One job worked must count exactly once, however many queues share
        // the Application's event manager.
        $this->assertEquals(1, $registry->getRecord()->getJobsProcessed());
    }

    public function testAttachingTwiceDoesNotDoubleCount()
    {
        $queue    = Queue::fake('billing');
        $worker   = Worker::create($queue);
        $registry = new WorkerRegistry(new RegistryMemory());
        $registry->register('w', ['billing'], WorkerRecord::MODE_DAEMON);

        $registry->attachTo($worker);
        $registry->attachTo($worker);

        $queue->addJob(Job::create(function(){ return 1; }));
        $queue->work();

        $this->assertEquals(1, $registry->getRecord()->getJobsProcessed());
    }

}
