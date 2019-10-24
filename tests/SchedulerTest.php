<?php

namespace Pop\Queue\Test;

use Pop\Queue\Processor;
use PHPUnit\Framework\TestCase;

class SchedulerTest extends TestCase
{

    public function testAddSchedules()
    {
        $schedule1 = new Processor\Jobs\Schedule();
        $schedule2 = new Processor\Jobs\Schedule(null, 'GMT');

        $scheduler = new Processor\Scheduler();
        $scheduler->addSchedules([$schedule1, $schedule2]);

        $this->assertEquals('GMT', $schedule2->getTimezone());
        $this->assertTrue($scheduler->hasSchedules());
        $this->assertTrue($scheduler->hasSchedule(0));
        $this->assertFalse($schedule1->hasJob());
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

    public function testGetCronValues()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->cron('*/5 * * * *');
        $this->assertEquals(['*/5'], $schedule->getMinutes());
        $this->assertEquals(['*'], $schedule->getHours());
        $this->assertEquals(['*'], $schedule->getDaysOfTheMonth());
        $this->assertEquals(['*'], $schedule->getMonths());
        $this->assertEquals(['*'], $schedule->getDaysOfTheWeek());
    }

    public function testGetMultipleMinutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->minutes('15,30');
        $this->assertEquals(['15', '30'], $schedule->getMinutes());
    }

    public function testGetEvery5Minutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->every5Minutes();
        $this->assertEquals(['*/5'], $schedule->getMinutes());
    }

    public function testGetEvery10Minutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->every10Minutes();
        $this->assertEquals(['*/10'], $schedule->getMinutes());
    }

    public function testGetEvery15Minutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->every15Minutes();
        $this->assertEquals(['*/15'], $schedule->getMinutes());
    }

    public function testGetEvery20Minutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->every20Minutes();
        $this->assertEquals(['*/20'], $schedule->getMinutes());
    }

    public function testGetEvery30Minutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->every30Minutes();
        $this->assertEquals(['*/30'], $schedule->getMinutes());
    }

    public function testGetMinutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->minutes(12);
        $this->assertEquals(['12'], $schedule->getMinutes());
    }

    public function testGetHourly()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->hourly();
        $this->assertEquals(['*'], $schedule->getHours());
    }

    public function testGetHourlyWithMinutes()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->hourly(15);
        $this->assertEquals(['*'], $schedule->getHours());
        $this->assertEquals(['15'], $schedule->getMinutes());
    }

    public function testGetDaily()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->daily(8);
        $this->assertEquals(['8'], $schedule->getHours());
    }

    public function testGetDailyAt()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->dailyAt('10:05');
        $this->assertEquals(['10'], $schedule->getHours());
        $this->assertEquals(['5'], $schedule->getMinutes());
    }

    public function testGetWeekly()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->weekly(0);
        $this->assertEquals(['0'], $schedule->getDaysOfTheWeek());
    }

    public function testGetWeeklyWithHours()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->weekly(0, 8, 15);
        $this->assertEquals(['0'], $schedule->getDaysOfTheWeek());
        $this->assertEquals(['8'], $schedule->getHours());
        $this->assertEquals(['15'], $schedule->getMinutes());
    }

    public function testGetMonthly()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->monthly(1);
        $this->assertEquals(['1'], $schedule->getDaysOfTheMonth());
    }

    public function testGetMonthlyWithHours()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->monthly(1, 8, 15);
        $this->assertEquals(['1'], $schedule->getDaysOfTheMonth());
        $this->assertEquals(['8'], $schedule->getHours());
        $this->assertEquals(['15'], $schedule->getMinutes());
    }

    public function testGetQuarterly()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->quarterly();
        $this->assertEquals(['*/3'], $schedule->getMonths());
    }

    public function testGetQuarterlyWithHours()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->quarterly(8, 15);
        $this->assertEquals(['*/3'], $schedule->getMonths());
        $this->assertEquals(['8'], $schedule->getHours());
        $this->assertEquals(['15'], $schedule->getMinutes());
    }

    public function testGetYearly()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->yearly();
        $this->assertEquals(['1'], $schedule->getMonths());
        $this->assertEquals(['1'], $schedule->getDaysOfTheMonth());
    }

    public function testGetYearlyWithHours()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->yearly(false, 8, 15);
        $this->assertEquals(['1'], $schedule->getMonths());
        $this->assertEquals(['1'], $schedule->getDaysOfTheMonth());
        $this->assertEquals(['8'], $schedule->getHours());
        $this->assertEquals(['15'], $schedule->getMinutes());
    }

    public function testGetWeekdays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->weekdays();
        $this->assertEquals(['1', '2', '3', '4', '5'], $schedule->getDaysOfTheWeek());
    }

    public function testGetWeekends()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->weekends();
        $this->assertEquals(['0', '6'], $schedule->getDaysOfTheWeek());
    }

    public function testGetSundays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->sundays();
        $this->assertEquals(['0'], $schedule->getDaysOfTheWeek());
    }

    public function testGetMondays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->mondays();
        $this->assertEquals(['1'], $schedule->getDaysOfTheWeek());
    }

    public function testGetTuesdays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->tuesdays();
        $this->assertEquals(['2'], $schedule->getDaysOfTheWeek());
    }

    public function testGetWednesdays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->wednesdays();
        $this->assertEquals(['3'], $schedule->getDaysOfTheWeek());
    }

    public function testGetThursdays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->thursdays();
        $this->assertEquals(['4'], $schedule->getDaysOfTheWeek());
    }

    public function testGetFridays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->fridays();
        $this->assertEquals(['5'], $schedule->getDaysOfTheWeek());
    }

    public function testGetSaturdays()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->saturdays();
        $this->assertEquals(['6'], $schedule->getDaysOfTheWeek());
    }

    public function testGetBetween()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->between(9, 17);
        $this->assertEquals(['9-17'], $schedule->getHours());
    }

    public function testGetRunUntil()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->runUntil('2019-12-31 23:59:59');
        $this->assertTrue($schedule->hasRunUntil());
        $this->assertEquals('2019-12-31 23:59:59', $schedule->getRunUntil());
    }

    public function testExpiredByDate()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->runUntil(date('Y-m-d H:i:s', time() - 10));
        $this->assertTrue($schedule->isExpired());
    }

    public function testExpiredByAttempts()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->runUntil(2);
        $this->assertTrue($schedule->isExpired(3));
    }

    public function testIsSatisfied1()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->minutes((int)date('i'));
        $this->assertTrue($schedule->isDue());
    }

    public function testIsSatisfied2()
    {
        $min = (int)date('i');
        $max = $min + 5;
        $schedule = new Processor\Jobs\Schedule();
        $schedule->minutes($min . '-' . $max);
        $this->assertTrue($schedule->isDue());
    }

    public function testIsSatisfied3()
    {
        $min = (int)date('i');
        $max = $min + 5;
        $schedule = new Processor\Jobs\Schedule();
        $schedule->cron('*/' . $min . ' * * * *');
        $this->assertTrue($schedule->isDue());
    }

    public function testIsSatisfied5()
    {
        $schedule = new Processor\Jobs\Schedule();
        $schedule->minutes((int)date('i') + 5);
        $this->assertFalse($schedule->isDue());
    }

}