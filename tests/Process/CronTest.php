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

    public function testGracePeriodDefaultsToUnlimited()
    {
        $this->assertEquals(-1, (new Cron())->getGracePeriod());
        $this->assertEquals(0, (new Cron('* * * * *', 0))->getGracePeriod());
    }

    public function testHasGracePeriodIsFalseOnlyWhenStrict()
    {
        $cron = new Cron('* * * * *');
        $this->assertTrue($cron->hasGracePeriod());  // -1 is the loosest setting, not the absence of one

        $this->assertTrue($cron->setGracePeriod(10)->hasGracePeriod());
        $this->assertFalse($cron->setGracePeriod(0)->hasGracePeriod());
    }

    public function testTasksPersistedBeforeTheRenameStillDeserialize()
    {
        // A task scheduled by an older version was stored with the property
        // named "buffer". Deserializing it must not fatal on the now-missing
        // typed $gracePeriod - it falls back to the declared default.
        $payload = 'O:22:"Pop\Queue\Process\Cron":8:{'
            . 's:11:"' . "\0" . '*' . "\0" . 'schedule";s:9:"* * * * *";'
            . 's:10:"' . "\0" . '*' . "\0" . 'seconds";a:0:{}'
            . 's:10:"' . "\0" . '*' . "\0" . 'minutes";a:1:{i:0;s:1:"*";}'
            . 's:8:"'  . "\0" . '*' . "\0" . 'hours";a:1:{i:0;s:1:"*";}'
            . 's:17:"' . "\0" . '*' . "\0" . 'daysOfTheMonth";a:1:{i:0;s:1:"*";}'
            . 's:9:"'  . "\0" . '*' . "\0" . 'months";a:1:{i:0;s:1:"*";}'
            . 's:16:"' . "\0" . '*' . "\0" . 'daysOfTheWeek";a:1:{i:0;s:1:"*";}'
            . 's:9:"'  . "\0" . '*' . "\0" . 'buffer";i:0;}';

        $cron = @unserialize($payload);

        $this->assertInstanceOf(Cron::class, $cron);
        $this->assertEquals(-1, $cron->getGracePeriod());
        $this->assertTrue($cron->evaluate(mktime(9, 35, 37)));
    }

    /**
     * evaluate() short-circuits on the first field that fails rather than
     * testing all six, and it no longer carries a dedicated '* * * * *'
     * branch (an all-wildcard schedule now passes every field test and lands
     * on the shared grace-period answer). Both are meant to be invisible from
     * the outside, so pin the answer for one representative time against every
     * field position - if a rewrite ever drops a field from the chain, exactly
     * one of these flips.
     */
    public function testEveryFieldIndependentlyRulesOutASchedule()
    {
        // Wed 2024-03-13 14:25:30
        $time = mktime(14, 25, 30, 3, 13, 2024);

        $this->assertTrue(Cron::create('25 14 13 3 3')->evaluate($time));

        $this->assertFalse(Cron::create('26 14 13 3 3')->evaluate($time), 'minute');
        $this->assertFalse(Cron::create('25 15 13 3 3')->evaluate($time), 'hour');
        $this->assertFalse(Cron::create('25 14 14 3 3')->evaluate($time), 'day of month');
        $this->assertFalse(Cron::create('25 14 13 4 3')->evaluate($time), 'month');
        $this->assertFalse(Cron::create('25 14 13 3 4')->evaluate($time), 'day of week');

        // The seconds field only participates when the schedule actually has one.
        $this->assertTrue(Cron::create('30 25 14 13 3 3')->evaluate($time), 'seconds match');
        $this->assertFalse(Cron::create('31 25 14 13 3 3')->evaluate($time), 'seconds mismatch');
    }

    /**
     * The seconds field must stay out of the decision on a minute-granularity
     * schedule - there the grace period governs the seconds instead. A
     * five-field schedule is due for its whole minute by default, and only
     * within the grace window once one is set.
     */
    public function testSecondsAreGovernedByGracePeriodOnMinuteSchedules()
    {
        $due = mktime(14, 25, 0, 3, 13, 2024);

        // Default grace of -1: due for the entire minute.
        $this->assertTrue(Cron::create('25 14 13 3 3')->evaluate($due + 59));

        // Grace of 10: due only through the 10th second. Built with the
        // constructor rather than create(), which takes no grace-period argument.
        $this->assertTrue((new Cron('25 14 13 3 3', 10))->evaluate($due + 10));
        $this->assertFalse((new Cron('25 14 13 3 3', 10))->evaluate($due + 11));

        // Grace of 0: strict to the 00 second.
        $this->assertTrue((new Cron('25 14 13 3 3', 0))->evaluate($due));
        $this->assertFalse((new Cron('25 14 13 3 3', 0))->evaluate($due + 1));

        // An all-wildcard schedule takes the same grace-period path now that it
        // has no special case of its own.
        $this->assertTrue((new Cron('* * * * *', 5))->evaluate($due + 5));
        $this->assertFalse((new Cron('* * * * *', 5))->evaluate($due + 6));
    }

    /**
     * Fields set through the fluent helpers hold ints, while fields parsed out
     * of a schedule string hold strings. Both have to compare equal to the int
     * pulled off the timestamp, which is why the field test uses a loose
     * in_array().
     */
    public function testFluentIntFieldsAndParsedStringFieldsAgree()
    {
        $time = mktime(9, 0, 30, 3, 13, 2024);

        $fluent = Cron::create()->daily(9);
        $parsed = Cron::create($fluent->render());

        $this->assertEquals($parsed->evaluate($time), $fluent->evaluate($time));
        $this->assertTrue($fluent->evaluate($time));
        $this->assertFalse($fluent->evaluate($time + 3600));
    }

}