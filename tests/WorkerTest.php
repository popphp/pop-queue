<?php

namespace Pop\Queue\Test;

use Pop\Application;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;
use Pop\Queue\Worker;
use Pop\Queue\Queue;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{

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
        $this->assertTrue($queue->adapter()->hasFailedJobs());
        $worker->clearFailed('pop-queue');
        $this->assertFalse($queue->adapter()->hasFailedJobs());
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

        $queue1->addJob($job1);
        $queue2->addJob($job2);

        $worker = Worker::create([$queue1, $queue2]);
        $jobs = $worker->workAll();

        $this->assertTrue($queue1->adapter()->hasFailedJobs());
        $this->assertTrue($queue2->adapter()->hasFailedJobs());
        $worker->clearAllFailed();
        $this->assertFalse($queue1->adapter()->hasFailedJobs());
        $this->assertFalse($queue2->adapter()->hasFailedJobs());
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

}