<?php

namespace Pop\Queue\Test\Process;

use Pop\Queue\Process\Cron;
use PHPUnit\Framework\TestCase;

class CronTest extends TestCase
{

    public function testConstructor()
    {
        $cron = new Cron('* * * * *');
        $this->assertInstanceOf('Pop\Queue\Process\Cron', $cron);
        $this->assertTrue($cron->hasSchedule());
        $this->assertEquals('* * * * *', $cron->getSchedule());
    }

    public function testCreate()
    {
        $cron = Cron::create('* * * * *');
        $this->assertInstanceOf('Pop\Queue\Process\Cron', $cron);
        $this->assertTrue($cron->hasSchedule());
        $this->assertEquals('* * * * *', $cron->getSchedule());
    }

    public function testSeconds1()
    {
        $cron = Cron::create('* * * * * *');
        $this->assertTrue($cron->hasSeconds());
    }

    public function testSeconds2()
    {
        $cron = Cron::create();
        $cron->seconds('15,45');
        $this->assertTrue($cron->hasSeconds());
        $this->assertEquals(['15','45'], $cron->getSeconds());
    }

    public function testSeconds3()
    {
        $cron = Cron::create();
        $cron->seconds('15');
        $this->assertTrue($cron->hasSeconds());
        $this->assertEquals([15], $cron->getSeconds());
    }

    public function testMinutes()
    {
        $cron = Cron::create();
        $cron->minutes('15,45');
        $this->assertEquals(['15','45'], $cron->getMinutes());
    }

    public function testHours()
    {
        $cron = Cron::create();
        $cron->hours('8,9', '15,16');
        $this->assertEquals(['15','16'], $cron->getMinutes());
        $this->assertEquals(['8', '9'], $cron->getHours());
    }

    public function testHourly()
    {
        $cron = Cron::create();
        $cron->hourly('15');
        $this->assertEquals(['15'], $cron->getMinutes());
        $this->assertEquals(['*'], $cron->getHours());
    }

    public function testWeekly()
    {
        $cron = Cron::create();
        $cron->weekly('1');
        $this->assertEquals([0], $cron->getMinutes());
        $this->assertEquals([0], $cron->getHours());
        $this->assertEquals(['1'], $cron->getDaysOfTheWeek());
    }

    public function testMonthly()
    {
        $cron = Cron::create();
        $cron->monthly('1');
        $this->assertEquals([0], $cron->getMinutes());
        $this->assertEquals([0], $cron->getHours());
        $this->assertEquals(['1'], $cron->getDaysOfTheMonth());
    }

    public function testQuarterly()
    {
        $cron = Cron::create();
        $cron->quarterly(0, 0);
        $this->assertEquals([0], $cron->getMinutes());
        $this->assertEquals([0], $cron->getHours());
        $this->assertEquals(['1'], $cron->getDaysOfTheMonth());
        $this->assertEquals([1,4,7,10], $cron->getMonths());
    }

    public function testYearly()
    {
        $cron = Cron::create();
        $cron->yearly(true, 23, 59);
        $this->assertEquals([59], $cron->getMinutes());
        $this->assertEquals([23], $cron->getHours());
        $this->assertEquals(['*'], $cron->getDaysOfTheWeek());
        $this->assertEquals(['31'], $cron->getDaysOfTheMonth());
        $this->assertEquals(['12'], $cron->getMonths());
    }

    public function testRender()
    {
        $cron = Cron::create();
        $cron->yearly(true, 23, 59);
        $this->assertEquals('59 23 31 12 *', (string)$cron);
    }

    public function testRenderException()
    {
        $this->expectException('Pop\Queue\Process\Exception');
        $cron = Cron::create();
        $cronSchedule = $cron->render();
    }

    public function testEvaluate1()
    {
        $cron = Cron::create();
        $cron->everyMinute();
        $this->assertTrue($cron->evaluate('2023-11-02 23:00:00'));
    }

    public function testEvaluate2()
    {
        $cron = Cron::create('20,40 8,12 1,15 1,7 0,1');
        $this->assertTrue($cron->evaluate('2023-01-01 08:20:00', 10));
        $this->assertFalse($cron->evaluate('2023-01-01 08:22:00'));
    }

    public function testEvaluate3()
    {
        $cron = Cron::create('*/2 8,12 1,15 1,7 0,1');
        $this->assertTrue($cron->evaluate('2023-01-01 08:20:00', 10));
        $this->assertFalse($cron->evaluate('2023-01-01 08:23:00'));
    }

    public function testEvaluate4()
    {
        $cron = Cron::create('1-15 8,12 1,15 1,7 0,1');
        $this->assertTrue($cron->evaluate('2023-01-01 08:12:00', 10));
        $this->assertFalse($cron->evaluate('2023-01-01 08:22:00'));
    }

    public function testEvaluate5()
    {
        $cron = Cron::create('15,45 20,40 8,12 1,15 1,7 *');
        $this->assertTrue($cron->evaluate('2023-01-01 08:20:15'));
        $this->assertFalse($cron->evaluate('2023-01-01 08:20:10'));
    }

    public function testEvaluateException()
    {
        $this->expectException('Pop\Queue\Process\Exception');
        $cron = Cron::create();
        $cron->everyMinute();
        $this->assertTrue($cron->evaluate('BAD DATE'));
    }


}