<?php

namespace Pop\Queue\Test\Process;

use Pop\Queue\Process\Task;
use PHPUnit\Framework\TestCase;

class TaskTest extends TestCase
{

    public function testConstructor()
    {
        $task = new Task(function(){echo 1;});
        $this->assertInstanceOf('Pop\Queue\Process\Task', $task);
        $this->assertTrue($task->hasCron());
        $this->assertInstanceOf('Pop\Queue\Process\Cron', $task->getCron());
        $this->assertInstanceOf('Pop\Queue\Process\Cron', $task->cron());
    }

    public function testCreate()
    {
        $task = Task::create(function(){echo 1;});
        $this->assertInstanceOf('Pop\Queue\Process\Task', $task);
        $task->schedule('* * * * *');
        $this->assertEquals('* * * * *', $task->cron()->getSchedule());
    }

    public function testTaskId()
    {
        $task = Task::create(function(){echo 1;});
        $task->setTaskId(1);
        $this->assertTrue($task->hasTaskId());
        $this->assertEquals(1, $task->getTaskId());
    }

    public function testBuffer()
    {
        $task = Task::create(function(){echo 1;});
        $task->setBuffer(10);
        $this->assertEquals(10, $task->getBuffer());
        $task->buffer(15);
        $this->assertEquals(15, $task->getBuffer());
    }

    public function testEverySecond()
    {
        $task = Task::create(function(){echo 1;});
        $task->everySecond();
        $this->assertEquals(['*'], $task->getSeconds());
    }

    public function testEvery5Seconds()
    {
        $task = Task::create(function(){echo 1;});
        $task->every5Seconds();
        $this->assertEquals(['*/5'], $task->getSeconds());
    }

    public function testEvery10Seconds()
    {
        $task = Task::create(function(){echo 1;});
        $task->every10Seconds();
        $this->assertEquals(['*/10'], $task->getSeconds());
    }

    public function testEvery15Seconds()
    {
        $task = Task::create(function(){echo 1;});
        $task->every15Seconds();
        $this->assertEquals(['*/15'], $task->getSeconds());
    }

    public function testEvery20Seconds()
    {
        $task = Task::create(function(){echo 1;});
        $task->every20Seconds();
        $this->assertEquals(['*/20'], $task->getSeconds());
    }

    public function testEvery30Seconds()
    {
        $task = Task::create(function(){echo 1;});
        $task->every30Seconds();
        $this->assertEquals(['*/30'], $task->getSeconds());
    }

    public function testSeconds()
    {
        $task = Task::create(function(){echo 1;});
        $task->seconds('*/2');
        $this->assertEquals(['*/2'], $task->getSeconds());
    }

    public function testEveryMinute()
    {
        $task = Task::create(function(){echo 1;});
        $task->everyMinute();
        $this->assertEquals(['*'], $task->getMinutes());
    }

    public function testEvery5Minutes()
    {
        $task = Task::create(function(){echo 1;});
        $task->every5Minutes();
        $this->assertEquals(['*/5'], $task->getMinutes());
    }

    public function testEvery10Minutes()
    {
        $task = Task::create(function(){echo 1;});
        $task->every10Minutes();
        $this->assertEquals(['*/10'], $task->getMinutes());
    }

    public function testEvery15Minutes()
    {
        $task = Task::create(function(){echo 1;});
        $task->every15Minutes();
        $this->assertEquals(['*/15'], $task->getMinutes());
    }

    public function testEvery20Minutes()
    {
        $task = Task::create(function(){echo 1;});
        $task->every20Minutes();
        $this->assertEquals(['*/20'], $task->getMinutes());
    }

    public function testEvery30Minutes()
    {
        $task = Task::create(function(){echo 1;});
        $task->every30Minutes();
        $this->assertEquals(['*/30'], $task->getMinutes());
    }

    public function testMinutes()
    {
        $task = Task::create(function(){echo 1;});
        $task->minutes('*/2');
        $this->assertEquals(['*/2'], $task->getMinutes());
    }

    public function testHours()
    {
        $task = Task::create(function(){echo 1;});
        $task->hours('*/2');
        $this->assertEquals(['*/2'], $task->getHours());
    }

    public function testHourly()
    {
        $task = Task::create(function(){echo 1;});
        $task->hourly();
        $this->assertEquals(['*'], $task->getHours());
        $this->assertEquals([0], $task->getMinutes());
    }

    public function testDaily()
    {
        $task = Task::create(function(){echo 1;});
        $task->daily(8);
        $this->assertEquals(['*'], $task->getDaysOfTheWeek());
        $this->assertEquals([8], $task->getHours());
        $this->assertEquals([0], $task->getMinutes());
    }

    public function testDailyAt()
    {
        $task = Task::create(function(){echo 1;});
        $task->dailyAt('8:00');
        $this->assertEquals(['8'], $task->getHours());
        $this->assertEquals([0], $task->getMinutes());
    }

    public function testWeekly()
    {
        $task = Task::create(function(){echo 1;});
        $task->weekly(1, 8, 0);
        $this->assertEquals([1], $task->getDaysOfTheWeek());
        $this->assertEquals([8], $task->getHours());
        $this->assertEquals([0], $task->getMinutes());
    }

    public function testMonthly()
    {
        $task = Task::create(function(){echo 1;});
        $task->monthly(1, 0, 0);
        $this->assertEquals(['*'], $task->getMonths());
        $this->assertEquals([1], $task->getDaysOfTheMonth());
        $this->assertEquals([0], $task->getHours());
        $this->assertEquals([0], $task->getMinutes());
    }

    public function testQuarterly()
    {
        $task = Task::create(function(){echo 1;});
        $task->quarterly();
        $this->assertEquals([1,4,7,10], $task->getMonths());
    }

    public function testYearly()
    {
        $task = Task::create(function(){echo 1;});
        $task->yearly();
        $this->assertEquals([1], $task->getMonths());
        $this->assertEquals([1], $task->getDaysOfTheMonth());
    }

    public function testWeekdays()
    {
        $task = Task::create(function(){echo 1;});
        $task->weekdays();
        $this->assertEquals(['1', '2', '3', '4', '5'], $task->getDaysOfTheWeek());
    }

    public function testWeekends()
    {
        $task = Task::create(function(){echo 1;});
        $task->weekends();
        $this->assertEquals(['0', '6'], $task->getDaysOfTheWeek());
    }

    public function testSundays()
    {
        $task = Task::create(function(){echo 1;});
        $task->sundays();
        $this->assertEquals(['0'], $task->getDaysOfTheWeek());
    }

    public function testMondays()
    {
        $task = Task::create(function(){echo 1;});
        $task->mondays();
        $this->assertEquals(['1'], $task->getDaysOfTheWeek());
    }

    public function testTuesdays()
    {
        $task = Task::create(function(){echo 1;});
        $task->tuesdays();
        $this->assertEquals(['2'], $task->getDaysOfTheWeek());
    }

    public function testWednesdays()
    {
        $task = Task::create(function(){echo 1;});
        $task->wednesdays();
        $this->assertEquals(['3'], $task->getDaysOfTheWeek());
    }

    public function testThursdays()
    {
        $task = Task::create(function(){echo 1;});
        $task->thursdays();
        $this->assertEquals(['4'], $task->getDaysOfTheWeek());
    }

    public function testFridays()
    {
        $task = Task::create(function(){echo 1;});
        $task->fridays();
        $this->assertEquals(['5'], $task->getDaysOfTheWeek());
    }

    public function testSaturdays()
    {
        $task = Task::create(function(){echo 1;});
        $task->saturdays();
        $this->assertEquals(['6'], $task->getDaysOfTheWeek());
    }

    public function testBetween()
    {
        $task = Task::create(function(){echo 1;});
        $task->between(8, 12);
        $this->assertEquals(['8-12'], $task->getHours());
    }

}