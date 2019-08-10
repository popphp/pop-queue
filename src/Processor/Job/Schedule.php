<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor\Job;

/**
 * Job schedule class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Schedule
{

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
    protected $timezone = 'America/Chicago';

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
            $this->minutes = explode(',' , $minutes);
        } else if (is_numeric($minutes)) {
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
        $minute = (int)date('i');
        $hour   = (int)date('G');
        $dom    = (int)date('j');
        $month  = (int)date('n');
        $dow    = (int)date('w');
    }

}