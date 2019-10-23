<?php

namespace Pop\Queue\Test;

use Pop\Queue\Processor;
use PHPUnit\Framework\TestCase;

class SchedulerTest extends TestCase
{

    public function testAddSchedules()
    {
        $schedule1 = new Processor\Jobs\Schedule();
        $schedule2 = new Processor\Jobs\Schedule();

        $scheduler = new Processor\Scheduler();
        $scheduler->addSchedules([$schedule1, $schedule2]);

        $this->assertTrue($scheduler->hasSchedules());
        $this->assertTrue($scheduler->hasSchedule(0));
        $this->assertInstanceOf('Pop\Queue\Processor\Jobs\Schedule', $scheduler->getSchedule(0));
    }

    public function testProcessNextException()
    {
        $job1 = new Processor\Jobs\Job(function() {
            throw new \Exception('Whoops!');
        });
        $scheduler = new Processor\Scheduler();
        $scheduler->addJob($job1)->everyMinute();

        $scheduler->processNext();
        $this->assertTrue($scheduler->hasFailedJobs());
    }

}