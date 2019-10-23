<?php

namespace Pop\Queue\Test;

use Pop\Queue\Processor;
use PHPUnit\Framework\TestCase;

class WorkerTest extends TestCase
{

    public function testIsFilo()
    {
        $worker = new Processor\Worker(Processor\Worker::FIFO);
        $this->assertTrue($worker->isFifo());
    }

    public function testAddJobs()
    {
        $job1  = new Processor\Jobs\Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $job2  = new Processor\Jobs\Job(function() {
            echo 'This is job #2' . PHP_EOL;
        });
        $worker = new Processor\Worker();
        $worker->addJobs([$job1, $job2]);

        $this->assertTrue($worker->hasJobs());
    }

    public function testGetJob()
    {
        $job1 = new Processor\Jobs\Job(function() {
            echo 'This is job #1' . PHP_EOL;
        });
        $worker = new Processor\Worker();
        $worker->addJob($job1);

        $this->assertInstanceOf('Pop\Queue\Processor\Jobs\Job', $worker->getJob(0));
    }

    public function testProcessNextException()
    {
        $job1 = new Processor\Jobs\Job(function() {
            throw new \Exception('Whoops!');
        });
        $worker = new Processor\Worker();
        $worker->addJob($job1);

        $worker->processNext();
        $this->assertTrue($worker->hasFailedJobs());
    }

}