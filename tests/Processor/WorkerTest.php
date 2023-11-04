<?php

namespace Pop\Queue\Test\Processor;

use Pop\Queue\Processor\Worker;
use Pop\Queue\Processor\Job;
use Pop\Queue\Processor\Task;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{

    public function testGetJobResults()
    {
        $worker = new Worker();

        $this->assertEquals(0, count($worker->getJobResults()));
        $this->assertEmpty($worker->getJobResult(0));
        $this->assertFalse($worker->hasJobResults());
    }

    public function testGetCompletedJobResults()
    {
        $worker = new Worker();

        $this->assertEquals(0, count($worker->getCompletedJobs()));
        $this->assertEmpty($worker->getCompletedJob(0));
        $this->assertFalse($worker->hasCompletedJobs());
    }

    public function testGetFailedJobResults()
    {
        $worker = new Worker();

        $this->assertEquals(0, count($worker->getFailedJobs()));
        $this->assertEmpty($worker->getFailedJob(0));
        $this->assertFalse($worker->hasFailedJobs());
    }

    public function testGetFailedExceptions()
    {
        $worker = new Worker();

        $this->assertEquals(0, count($worker->getFailedExceptions()));
        $this->assertEmpty($worker->getFailedException(0));
        $this->assertFalse($worker->hasFailedExceptions());
    }

    public function testIsFilo()
    {
        $worker = new Worker(Worker::FIFO);
        $this->assertTrue($worker->isFifo());
    }

    public function testAddJobs()
    {
        $job1 = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $job2 = new Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });
        $worker = new Worker();
        $worker->addJobs([$job1, $job2], 1);

        $this->assertTrue($worker->hasJobs());
        $this->assertEquals(1, $job1->getMaxAttempts());
        $this->assertEquals(1, $job2->getMaxAttempts());
    }

    public function testAddTasks()
    {
        $task1 = new Task(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $task2 = new Task(function() {
            echo 'This is job #2' . PHP_EOL;
        });
        $worker = new Worker();
        $worker->addTasks([$task1, $task2]);

        $this->assertTrue($worker->hasJobs());
    }

    public function testGetJob()
    {
        $job1 = new Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $worker = new Worker();
        $worker->addJob($job1);

        $this->assertInstanceOf('Pop\Queue\Processor\Job', $worker->getJob(0));
    }

    public function testProcessResults()
    {
        $job1 = new Job(function() {
            return 123;
        });
        $worker = new Worker();
        $worker->addJob($job1);
        $worker->processNext();
        $this->assertTrue($worker->hasCompletedJobs());
        $this->assertEquals(123, $worker->getJobResult(0));
    }

    public function testProcessTasks1()
    {
        $task1 = Task::create(function() {
            echo 'This is job #1' . PHP_EOL;
        })->everyMinute();
        $worker = new Worker();
        $worker->addTask($task1);
        $worker->processNext();
        $this->assertFalse($worker->hasCompletedJobs());
    }

    public function testProcessTasks2()
    {
        $task1 = Task::create(function() {
            echo 'This is job #1' . PHP_EOL;
        })->everySecond();
        $worker = new Worker();
        $worker->addTask($task1);

        ob_start();
        $worker->processNext();
        $result = ob_get_clean();

        $this->assertStringContainsString('This is job #1', $result);
        $this->assertTrue($worker->hasCompletedJobs());
    }

    public function testProcessNextException()
    {
        $job1 = new Job(function() {
            throw new \Exception('Whoops!');
        });
        $worker = new Worker();
        $worker->addJob($job1);

        $worker->processNext();
        $this->assertTrue($worker->hasFailedJobs());
    }

}