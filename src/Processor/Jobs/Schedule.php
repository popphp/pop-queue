<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor\Jobs;

/**
 * Job schedule class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
 */
class Schedule
{

    /**
     * The job the schedule belongs to
     * @var AbstractJob
     */
    protected $job = null;

    /**
     * Minutes
     * @var array
     */
    protected $minutes = [];

    /**
     * Hours
     * @var array
     */
    protected $hours = [];

    /**
     * Days of the month
     * @var array
     */
    protected $daysOfTheMonth = [];

    /**
     * Months
     * @var array
     */
    protected $months = [];

    /**
     * Days of the week
     * @var array
     */
    protected $daysOfTheWeek = [];

    /**
     * Timezone
     * @var string
     */
    protected $timezone = null;

    /**
     * Run until property
     * @var int|string
     */
    protected $runUntil = null;

    /**
     * Constructor
     *
     * Instantiate the job schedule object
     *
     * @param  AbstractJob $job
     * @param  string      $timezone
     */
    public function __construct(AbstractJob $job = null, $timezone = null)
    {
        if (null !== $job) {
            $this->setJob($job);
        }

        if (null !== $timezone) {
            $this->setTimezone($timezone);
        } else {
            $this->setTimezone(date('e'));
        }
    }

    /**
     * Set job
     *
     * @param  AbstractJob $job
     * @return Schedule
     */
    public function setJob(AbstractJob $job)
    {
        $this->job = $job;
        return $this;
    }

    /**
     * Get job
     *
     * @return AbstractJob
     */
    public function getJob()
    {
        return $this->job;
    }

    /**
     * Has job
     *
     * @return boolean
     */
    public function hasJob()
    {
        return (null !== $this->job);
    }

    /**
     * Set job schedule to custom cron schedule
     *
     *   min  hour  dom  month  dow
     *    *    *     *     *     *
     *
     * @param  mixed $cronSchedule
     * @return Schedule
     */
    public function cron($cronSchedule)
    {
        if (substr_count($cronSchedule, ' ') == 4) {
            list($min, $hour, $dom, $month, $dow) = explode(' ', $cronSchedule);

            $this->minutes        = [$min];
            $this->hours          = [$hour];
            $this->daysOfTheMonth = [$dom];
            $this->months         = [$month];
            $this->daysOfTheWeek  = [$dow];
        }

        return $this;
    }

    /**
     * Get minutes
     *
     * @return array
     */
    public function getMinutes()
    {
        return $this->minutes;
    }

    /**
     * Get hours
     *
     * @return array
     */
    public function getHours()
    {
        return $this->hours;
    }

    /**
     * Get days of the month
     *
     * @return array
     */
    public function getDaysOfTheMonth()
    {
        return $this->daysOfTheMonth;
    }

    /**
     * Get months
     *
     * @return array
     */
    public function getMonths()
    {
        return $this->months;
    }

    /**
     * Get days of the week
     *
     * @return array
     */
    public function getDaysOfTheWeek()
    {
        return $this->daysOfTheWeek;
    }

    /**
     * Set job schedule to every minute
     *
     * @return Schedule
     */
    public function everyMinute()
    {
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to every 5 minutes
     *
     * @return Schedule
     */
    public function every5Minutes()
    {
        $this->minutes        = ['*/5'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to every 10 minutes
     *
     * @return Schedule
     */
    public function every10Minutes()
    {
        $this->minutes        = ['*/10'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to every 15 minutes
     *
     * @return Schedule
     */
    public function every15Minutes()
    {
        $this->minutes        = ['*/15'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];
        return $this;
    }

    /**
     * Set job schedule to every 20 minutes
     *
     * @return Schedule
     */
    public function every20Minutes()
    {
        $this->minutes        = ['*/20'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to every 30 minutes
     *
     * @return Schedule
     */
    public function every30Minutes()
    {
        $this->minutes        = ['*/30'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to by specific minutes
     *
     * @param  mixed $minutes
     * @return Schedule
     */
    public function minutes($minutes)
    {
        if (is_string($minutes) && (strpos($minutes, ',') !== false)) {
            $minutes = explode(',' , $minutes);
        } else if (is_numeric($minutes)) {
            $minutes = [(int)$minutes];
        } else {
            $minutes = [$minutes];
        }

        $this->minutes        = array_map('trim', $minutes);
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to hourly
     *
     * @param  mixed $minute
     * @return Schedule
     */
    public function hourly($minute = null)
    {
        if (null === $minute) {
            $this->minutes = ['0'];
        } else {
            $this->minutes($minute);
        }

        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to daily
     *
     * @param  mixed $hours
     * @param  mixed $minute
     * @return Schedule
     */
    public function daily($hours, $minute = null)
    {
        if (null === $minute) {
            $this->minutes = ['0'];
        } else {
            $this->minutes($minute);
        }

        $this->hours          = [$hours];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to daily at specific time, i.e. 14:30
     *
     * @param  string $time
     * @return Schedule
     */
    public function dailyAt($time)
    {
        list($hour, $minute) = explode(':', $time);
        $this->daily($hour, $minute);
        return $this;
    }

    /**
     * Set job schedule to weekly
     *
     * @param  mixed $day
     * @param  mixed $hours
     * @param  mixed $minute
     * @return Schedule
     */
    public function weekly($day, $hours = null, $minute = null)
    {
        if (null === $minute) {
            $this->minutes = ['0'];
        } else {
            $this->minutes($minute);
        }

        if (null === $hours) {
            $this->hours = ['0'];
        } else {
            $this->hours = [$hours];
        }

        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = [$day];

        return $this;
    }

    /**
     * Set job schedule to monthly
     *
     * @param  mixed $day
     * @param  mixed $hours
     * @param  mixed $minute
     * @return Schedule
     */
    public function monthly($day, $hours = null, $minute = null)
    {
        if (null === $minute) {
            $this->minutes = ['0'];
        } else {
            $this->minutes($minute);
        }

        if (null === $hours) {
            $this->hours = ['0'];
        } else {
            $this->hours = [$hours];
        }

        $this->daysOfTheMonth = [$day];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to quarterly
     *
     * @param  mixed $hours
     * @param  mixed $minute
     * @return Schedule
     */
    public function quarterly($hours = null, $minute = null)
    {
        if (null === $minute) {
            $this->minutes = ['0'];
        } else {
            $this->minutes($minute);
        }

        if (null === $hours) {
            $this->hours = ['0'];
        } else {
            $this->hours = [$hours];
        }

        $this->daysOfTheMonth = ['1'];
        $this->months         = ['*/3'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to yearly
     *
     * @param  boolean $endOfYear
     * @param  mixed $hours
     * @param  mixed $minute
     * @return Schedule
     */
    public function yearly($endOfYear = false, $hours = null, $minute = null)
    {
        if (null === $minute) {
            $this->minutes = ['0'];
        } else {
            $this->minutes($minute);
        }

        if (null === $hours) {
            $this->hours = ['0'];
        } else {
            $this->hours = [$hours];
        }

        $this->daysOfTheMonth = ($endOfYear) ? ['31'] : ['1'];
        $this->months         = ($endOfYear) ? ['12'] : ['1'];
        $this->daysOfTheWeek  = ['*'];

        return $this;
    }

    /**
     * Set job schedule to weekdays
     *
     * @return Schedule
     */
    public function weekdays()
    {
        $this->daysOfTheWeek = ['1', '2', '3', '4', '5'];
        return $this;
    }

    /**
     * Set job schedule to weekends
     *
     * @return Schedule
     */
    public function weekends()
    {
        $this->daysOfTheWeek = ['0', '6'];
        return $this;
    }

    /**
     * Set job schedule to Sundays
     *
     * @return Schedule
     */
    public function sundays()
    {
        $this->daysOfTheWeek = ['0'];
        return $this;
    }

    /**
     * Set job schedule to Mondays
     *
     * @return Schedule
     */
    public function mondays()
    {
        $this->daysOfTheWeek = ['1'];
        return $this;
    }

    /**
     * Set job schedule to Tuesdays
     *
     * @return Schedule
     */
    public function tuesdays()
    {
        $this->daysOfTheWeek = ['2'];
        return $this;
    }

    /**
     * Set job schedule to Wednesdays
     *
     * @return Schedule
     */
    public function wednesdays()
    {
        $this->daysOfTheWeek = ['3'];
        return $this;
    }

    /**
     * Set job schedule to Thursdays
     *
     * @return Schedule
     */
    public function thursdays()
    {
        $this->daysOfTheWeek = ['4'];
        return $this;
    }

    /**
     * Set job schedule to Fridays
     *
     * @return Schedule
     */
    public function fridays()
    {
        $this->daysOfTheWeek = ['5'];
        return $this;
    }

    /**
     * Set job schedule to Saturdays
     *
     * @return Schedule
     */
    public function saturdays()
    {
        $this->daysOfTheWeek = ['6'];
        return $this;
    }

    /**
     * Set job schedule to between two hours
     *
     * @param  int $start
     * @param  int $end
     * @return Schedule
     */
    public function between($start, $end)
    {
        $this->hours = [$start . '-' . $end];
        return $this;
    }

    /**
     * Set the run until property
     *
     * @param  int|string $runUntil
     * @return Schedule
     */
    public function runUntil($runUntil)
    {
        $this->runUntil = $runUntil;
        return $this;
    }

    /**
     * Has run until
     *
     * @return boolean
     */
    public function hasRunUntil()
    {
        return (null !== $this->runUntil);
    }

    /**
     * Get run until value
     *
     * @return int|string
     */
    public function getRunUntil()
    {
        return $this->runUntil;
    }

    /**
     * Set job timezone
     *
     * @param  string $timezone
     * @return Schedule
     */
    public function setTimezone($timezone)
    {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * Get job timezone
     *
     * @return string
     */
    public function getTimezone()
    {
        return $this->timezone;
    }

    /**
     * Determine if the schedule is due
     *
     * @return boolean
     */
    public function isDue()
    {
        $minuteSatisfied = $this->isSatisfied($this->minutes, (int)date('i'));
        $hourSatisfied   = $this->isSatisfied($this->hours, (int)date('G'));
        $domSatisfied    = $this->isSatisfied($this->daysOfTheMonth, (int)date('j'));
        $monthSatisfied  = $this->isSatisfied($this->months, (int)date('n'));
        $dowSatisfied    = $this->isSatisfied($this->daysOfTheWeek, (int)date('w'));

        return ($minuteSatisfied && $hourSatisfied && $domSatisfied && $monthSatisfied && $dowSatisfied);
    }

    /**
     * Determine if the schedule is expired
     *
     * @param  int $attempts
     * @return boolean
     */
    public function isExpired($attempts = null)
    {
        if (is_string($this->runUntil) && (strtotime($this->runUntil) !== false)) {
            return (time() >= strtotime($this->runUntil));
        } else {
            return ($attempts >= $this->runUntil);
        }
    }

    /**
     * Determine if the value satisfies the expression
     *
     * @param  array $values
     * @param  mixed $value
     * @return boolean
     */
    protected function isSatisfied(array $values, $value)
    {
        if (!empty($values)) {
            if (in_array('*', $values)) {
                return true;
            }
            if (in_array((string)$value, $values)) {
                return true;
            }
            foreach ($values as $expression) {
                if (strpos($expression, '-') !== false) {
                    list($min, $max) = explode('-', $expression);
                    if (($value >= $min) || ($value <= $max)) {
                        return true;
                    }
                }
                if (strpos($expression, '/') !== false) {
                    $step = (int)substr($expression, (strpos($expression, '/') + 1));
                    if (($value % $step) == 0) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

}