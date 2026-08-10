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

}
