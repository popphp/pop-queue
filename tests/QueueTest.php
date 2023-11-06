<?php

namespace Pop\Queue\Test;

use Pop\Queue\Queue;
use Pop\Queue\Processor\Job;
use Pop\Queue\Processor\Task;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{

    public function testGetJobResults()
    {
        $queue = new Queue();

        $this->assertEquals(0, count($queue->getJobResults()));
        $this->assertEmpty($queue->getJobResult(0));
        $this->assertFalse($queue->hasJobResults());
    }

    public function testGetCompletedJobResults()
    {
        $queue = new Queue();

        $this->assertEquals(0, count($queue->getCompletedJobs()));
        $this->assertEmpty($queue->getCompletedJob(0));
        $this->assertFalse($queue->hasCompletedJobs());
    }

    public function testGetFailedJobResults()
    {
        $queue = new Queue();

        $this->assertEquals(0, count($queue->getFailedJobs()));
        $this->assertEmpty($queue->getFailedJob(0));
        $this->assertFalse($queue->hasFailedJobs());
    }

    public function testGetFailedExceptions()
    {
        $queue = new Queue();

        $this->assertEquals(0, count($queue->getFailedExceptions()));
        $this->assertEmpty($queue->getFailedException(0));
        $this->assertFalse($queue->hasFailedExceptions());
    }

    public function testIsFilo()
    {
        $queue = new Queue(Queue::FIFO);
        $this->assertTrue($queue->isFifo());
    }

    public function testAddJobs1()
    {
        $job1 = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $job2 = new Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });
        $queue = new Queue();
        $queue->addJobs([$job1, $job2], 1);

        $this->assertTrue($queue->hasJobs());
        $this->assertEquals(1, $job1->getMaxAttempts());
        $this->assertEquals(1, $job2->getMaxAttempts());
    }

    public function testAddJobs2()
    {
        $job1 = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $job2 = new Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });
        $queue = Queue::create([$job1, $job2]);

        $this->assertTrue($queue->hasJobs());
        $this->assertEquals(1, $job1->getMaxAttempts());
        $this->assertEquals(1, $job2->getMaxAttempts());
    }

    public function testAddJobs3()
    {
        $job1 = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $queue = Queue::create($job1);

        $this->assertTrue($queue->hasJobs());
        $this->assertEquals(1, $job1->getMaxAttempts());
    }

    public function testAddTasks1()
    {
        $task1 = new Task(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $task2 = new Task(function() {
            echo 'This is job #2' . PHP_EOL;
        });
        $queue = new Queue();
        $queue->addTasks([$task1, $task2]);

        $this->assertTrue($queue->hasJobs());
    }

    public function testAddTasks2()
    {
        $task1 = new Task(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $task2 = new Task(function() {
            echo 'This is job #2' . PHP_EOL;
        });
        $queue = Queue::create([$task1, $task2]);

        $this->assertTrue($queue->hasJobs());
    }

    public function testAddTasks3()
    {
        $task1 = new Task(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $queue = Queue::create($task1);

        $this->assertTrue($queue->hasJobs());
    }

    public function testGetJob()
    {
        $job1 = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $queue = new Queue();
        $queue->addJob($job1);

        $this->assertInstanceOf('Pop\Queue\Processor\Job', $queue->getJob(0));
    }

    public function testProcessResults()
    {
        $job1 = new Job(function() {
            return 123;
        });
        $queue = new Queue();
        $queue->addJob($job1);
        $queue->processNext();
        $this->assertTrue($queue->hasCompletedJobs());
        $this->assertEquals(123, $queue->getJobResult(0));
    }

    public function testProcessTasks1()
    {
        $task1 = Task::create(function() {
            echo 'This is job #1' . PHP_EOL;
        })->everyMinute();
        $queue = new Queue();
        $queue->addTask($task1);
        $queue->processNext();
        $this->assertFalse($queue->hasCompletedJobs());
    }

    public function testProcessTasks2()
    {
        $task1 = Task::create(function() {
            echo 'This is job #1' . PHP_EOL;
        })->everySecond();
        $queue = new Queue();
        $queue->addTask($task1);

        ob_start();
        $queue->processNext();
        $result = ob_get_clean();

        $this->assertStringContainsString('This is job #1', $result);
        $this->assertTrue($queue->hasCompletedJobs());
    }

    public function testProcessNextException()
    {
        $job1 = new Job(function() {
            throw new \Exception('Whoops!');
        });
        $queue = new Queue();
        $queue->addJob($job1);

        $queue->processNext();
        $this->assertTrue($queue->hasFailedJobs());
    }

}