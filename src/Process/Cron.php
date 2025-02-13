<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Process;

/**
 * Cron class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2025 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    2.1.2
 */
class Cron
{

    /**
     * Schedule string
     * @var ?string
     */
    protected ?string $schedule = null;

    /**
     * Seconds
     *  - Not a standard cron unit of time. The smallest time interval supported by cron is 1 minute.
     *    This is to support time intervals less than minute and down to 1 second.
     * @var array
     */
    protected array $seconds = [];

    /**
     * Minutes
     * @var array
     */
    protected array $minutes = [];

    /**
     * Hours
     * @var array
     */
    protected array $hours = [];

    /**
     * Days of the month
     * @var array
     */
    protected array $daysOfTheMonth = [];

    /**
     * Months
     * @var array
     */
    protected array $months = [];

    /**
     * Days of the week
     * @var array
     */
    protected array $daysOfTheWeek = [];

    /**
     * Time buffer
     * @var int
     */
    protected int $buffer = 0;

    /**
     * Constructor
     *
     * Instantiate the cron  object
     *
     * @param  ?string $schedule
     * @param  int     $buffer
     */
    public function __construct(?string $schedule = null, int $buffer = 0)
    {
        if ($schedule !== null) {
            $this->schedule($schedule);
        }
        $this->setBuffer($buffer);
    }

    /**
     * Factory
     *
     * @param  ?string $schedule
     * @return Cron
     */
    public static function create(?string $schedule = null): Cron
    {
        return new self($schedule);
    }

    /**
     * Set buffer
     *
     * @param  int $buffer
     * @return Cron
     */
    public function setBuffer(int $buffer): Cron
    {
        $this->buffer = $buffer;
        return $this;
    }

    /**
     * Get buffer
     *
     * @return int
     */
    public function getBuffer(): int
    {
        return $this->buffer;
    }

    /**
     * Has seconds
     *
     * @return bool
     */
    public function hasSeconds(): bool
    {
        return !empty($this->seconds);
    }

    /**
     * Get seconds
     *
     * @return array
     */
    public function getSeconds(): array
    {
        return $this->seconds;
    }

    /**
     * Get minutes
     *
     * @return array
     */
    public function getMinutes(): array
    {
        return $this->minutes;
    }

    /**
     * Get hours
     *
     * @return array
     */
    public function getHours(): array
    {
        return $this->hours;
    }

    /**
     * Get days of the month
     *
     * @return array
     */
    public function getDaysOfTheMonth(): array
    {
        return $this->daysOfTheMonth;
    }

    /**
     * Get months
     *
     * @return array
     */
    public function getMonths(): array
    {
        return $this->months;
    }

    /**
     * Get days of the week
     *
     * @return array
     */
    public function getDaysOfTheWeek(): array
    {
        return $this->daysOfTheWeek;
    }

    /**
     * Set cron schedule
     *
     *   min  hour  dom  month  dow
     *    *    *     *     *     *
     *
     *      - OR non-standard -
     *
     *    sec  min  hour  dom  month  dow
     *     *    *    *     *     *     *
     *
     * @param  string $schedule
     * @return Cron
     */
    public function schedule(string $schedule): Cron
    {
        $schedule = preg_replace('!\s+!', ' ', trim($schedule));

        if (substr_count($schedule, ' ') >= 4) {
            $this->schedule = $schedule;

            if (substr_count($schedule, ' ') == 5) {
                list($sec, $min, $hour, $dom, $month, $dow) = explode(' ', $this->schedule);
                $this->seconds = [$sec];
            } else {
                list($min, $hour, $dom, $month, $dow) = explode(' ', $this->schedule);
            }

            $this->minutes        = [$min];
            $this->hours          = [$hour];
            $this->daysOfTheMonth = [$dom];
            $this->months         = [$month];
            $this->daysOfTheWeek  = [$dow];
        }

        return $this;
    }

    /**
     * Get schedule string
     *
     * @return ?string
     */
    public function getSchedule(): ?string
    {
        return $this->schedule;
    }

    /**
     * Has schedule string
     *
     * @return bool
     */
    public function hasSchedule(): bool
    {
        return ($this->schedule !== null);
    }

    /**
     * Update cron schedule
     * @return Cron
     */
    public function updateSchedule(): Cron
    {
        $schedule = [];

        // Minutes
        if (count($this->seconds) > 1) {
            $schedule[] = implode(',', $this->seconds);
        } else if (isset($this->seconds[0])) {
            $schedule[] = $this->seconds[0];
        }

        // Minutes
        if (count($this->minutes) > 1) {
            $schedule[] = implode(',', $this->minutes);
        } else if (isset($this->minutes[0])) {
            $schedule[] = $this->minutes[0];
        }

        // Hours
        if (count($this->hours) > 1) {
            $schedule[] = implode(',', $this->hours);
        } else if (isset($this->hours[0])) {
            $schedule[] = $this->hours[0];
        }

        // DOM
        if (count($this->daysOfTheMonth) > 1) {
            $schedule[] = implode(',', $this->daysOfTheMonth);
        } else if (isset($this->daysOfTheMonth[0])) {
            $schedule[] = $this->daysOfTheMonth[0];
        }

        // Months
        if (count($this->months) > 1) {
            $schedule[] = implode(',', $this->months);
        } else if (isset($this->months[0])) {
            $schedule[] = $this->months[0];
        }

        // DOW
        if (count($this->daysOfTheWeek) > 1) {
            $schedule[] = implode(',', $this->daysOfTheWeek);
        } else if (isset($this->daysOfTheWeek[0])) {
            $schedule[] = $this->daysOfTheWeek[0];
        }

        if (empty($schedule)) {
            throw new Exception('Error: The cron schedule has not been set.');
        }

        $this->schedule = implode(' ', $schedule);
        return $this;
    }

    /**
     * Set job schedule to every second
     *
     * @return Cron
     */
    public function everySecond(): Cron
    {
        $this->seconds        = ['*'];
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 5 seconds
     *
     * @return Cron
     */
    public function every5Seconds(): Cron
    {
        $this->seconds        = ['*/5'];
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 10 seconds
     *
     * @return Cron
     */
    public function every10Seconds(): Cron
    {
        $this->seconds        = ['*/10'];
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 15 seconds
     *
     * @return Cron
     */
    public function every15Seconds(): Cron
    {
        $this->seconds        = ['*/15'];
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 20 seconds
     *
     * @return Cron
     */
    public function every20Seconds(): Cron
    {
        $this->seconds        = ['*/20'];
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 30 seconds
     *
     * @return Cron
     */
    public function every30Seconds(): Cron
    {
        $this->seconds        = ['*/30'];
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to by specific seconds
     *
     * @param  mixed $seconds
     * @return Cron
     */
    public function seconds(mixed $seconds): Cron
    {
        if (is_string($seconds) && (str_contains($seconds, ','))) {
            $seconds = explode(',' , $seconds);
        } else if (is_numeric($seconds)) {
            $seconds = [(int)$seconds];
        } else {
            $seconds = [$seconds];
        }

        $this->seconds        = array_map('trim', $seconds);
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every minute
     *
     * @return Cron
     */
    public function everyMinute(): Cron
    {
        $this->minutes        = ['*'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 5 minutes
     *
     * @return Cron
     */
    public function every5Minutes(): Cron
    {
        $this->minutes        = ['*/5'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 10 minutes
     *
     * @return Cron
     */
    public function every10Minutes(): Cron
    {
        $this->minutes        = ['*/10'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 15 minutes
     *
     * @return Cron
     */
    public function every15Minutes(): Cron
    {
        $this->minutes        = ['*/15'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 20 minutes
     *
     * @return Cron
     */
    public function every20Minutes(): Cron
    {
        $this->minutes        = ['*/20'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to every 30 minutes
     *
     * @return Cron
     */
    public function every30Minutes(): Cron
    {
        $this->minutes        = ['*/30'];
        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to by specific minutes
     *
     * @param  mixed $minutes
     * @return Cron
     */
    public function minutes(mixed $minutes): Cron
    {
        if (is_string($minutes) && (str_contains($minutes, ','))) {
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

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to by specific hours
     *
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Cron
     */
    public function hours(mixed $hours, mixed $minutes = null): Cron
    {
        if ($minutes !== null) {
            $this->minutes($minutes);
        } else {
            $this->minutes = [0];
        }

        if (is_string($hours) && (str_contains($hours, ','))) {
            $hours = explode(',' , $hours);
        } else if (is_numeric($hours)) {
            $hours = [(int)$hours];
        } else {
            $hours = [$hours];
        }

        $this->hours          = array_map('trim', $hours);
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to hourly
     *
     * @param  mixed $minutes
     * @return Cron
     */
    public function hourly(mixed $minutes = null): Cron
    {
        if ($minutes !== null) {
            $this->minutes($minutes);
        } else {
            $this->minutes = [0];            
        }

        $this->hours          = ['*'];
        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to daily (alias to hours)
     *
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Cron
     */
    public function daily(mixed $hours, mixed $minutes = null): Cron
    {
        return $this->hours($hours, $minutes);
    }

    /**
     * Set job schedule to daily at specific time, i.e. 14:30
     *
     * @param  string $time
     * @return Cron
     */
    public function dailyAt(string $time): Cron
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
     * @param  mixed $minutes
     * @return Cron
     */
    public function weekly(mixed $day, mixed $hours = null, mixed $minutes = null): Cron
    {
        if ($minutes !== null) {
            $this->minutes($minutes);
        } else {
            $this->minutes = [0];
        }

        if ($hours !== null) {
            $this->hours = [$hours];
        } else {
            $this->hours = [0];
        }

        $this->daysOfTheMonth = ['*'];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = [$day];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to monthly
     *
     * @param  mixed $day
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Cron
     */
    public function monthly(mixed $day, mixed $hours = null, mixed $minutes = null): Cron
    {
        if ($minutes !== null) {
            $this->minutes($minutes);
        } else {
            $this->minutes = [0];
        }

        if ($hours !== null) {
            $this->hours = [$hours];
        } else {
            $this->hours = [0];
        }

        $this->daysOfTheMonth = [$day];
        $this->months         = ['*'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to quarterly
     *
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Cron
     */
    public function quarterly(mixed $hours = null, mixed $minutes = null): Cron
    {
        if ($minutes !== null) {
            $this->minutes($minutes);
        } else {
            $this->minutes = [0];
        }

        if ($hours !== null) {
            $this->hours = [$hours];
        } else {
            $this->hours = [0];
        }

        $this->daysOfTheMonth = ['1'];
        $this->months         = [1,4,7,10];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to yearly
     *
     * @param  bool $endOfYear
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Cron
     */
    public function yearly(bool $endOfYear = false, mixed $hours = null, mixed $minutes = null): Cron
    {
        if ($minutes !== null) {
            $this->minutes($minutes);
        } else {
            $this->minutes = [0];
        }

        if ($hours !== null) {
            $this->hours = [$hours];
        } else {
            $this->hours = [0];
        }

        $this->daysOfTheMonth = ($endOfYear) ? ['31'] : ['1'];
        $this->months         = ($endOfYear) ? ['12'] : ['1'];
        $this->daysOfTheWeek  = ['*'];

        return $this->updateSchedule();
    }

    /**
     * Set job schedule to weekdays
     *
     * @return Cron
     */
    public function weekdays(): Cron
    {
        $this->daysOfTheWeek = ['1', '2', '3', '4', '5'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to weekends
     *
     * @return Cron
     */
    public function weekends(): Cron
    {
        $this->daysOfTheWeek = ['0', '6'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to Sundays
     *
     * @return Cron
     */
    public function sundays(): Cron
    {
        $this->daysOfTheWeek = ['0'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to Mondays
     *
     * @return Cron
     */
    public function mondays(): Cron
    {
        $this->daysOfTheWeek = ['1'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to Tuesdays
     *
     * @return Cron
     */
    public function tuesdays(): Cron
    {
        $this->daysOfTheWeek = ['2'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to Wednesdays
     *
     * @return Cron
     */
    public function wednesdays(): Cron
    {
        $this->daysOfTheWeek = ['3'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to Thursdays
     *
     * @return Cron
     */
    public function thursdays(): Cron
    {
        $this->daysOfTheWeek = ['4'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to Fridays
     *
     * @return Cron
     */
    public function fridays(): Cron
    {
        $this->daysOfTheWeek = ['5'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to Saturdays
     *
     * @return Cron
     */
    public function saturdays(): Cron
    {
        $this->daysOfTheWeek = ['6'];
        return $this->updateSchedule();
    }

    /**
     * Set job schedule to between two hours
     *
     * @param  int $start
     * @param  int $end
     * @return Cron
     */
    public function between(int $start, int $end): Cron
    {
        $this->hours = [$start . '-' . $end];
        return $this->updateSchedule();
    }

    /**
     * Render the cron schedule string
     *
     * @return string
     */
    public function render(): string
    {
        if (empty($this->schedule)) {
            $this->updateSchedule();
        }
        return $this->schedule;
    }

    /**
     * Evaluate the set cron schedule value against a time value
     *
     * $buffer = 0;      strict evaluation to the 00 second
     * $buffer = 1-59;   gives up to a minute buffer to account for any delay in processing
     * $buffer = -1;     disregards the seconds value for a loose evaluation
     *
     * @param  mixed $time
     * @param  ?int  $buffer
     * @throws Exception
     * @return bool
     */
    public function evaluate(mixed $time = null, ?int $buffer = null): bool
    {
        if ($time === null) {
            $time = time();
        } else if (is_string($time)) {
            $time = strtotime($time);
            if ($time === false) {
                throw new Exception('Error: That time value is not valid.');
            }
        }

        if ($buffer !== null) {
            $this->setBuffer($buffer);
        }

        $second        = (int)date('s', $time);
        $minute        = (int)date('i', $time);
        $hour          = (int)date('G', $time);
        $dayOfTheMonth = (int)date('j', $time);
        $month         = (int)date('n', $time);
        $dayOfTheWeek  = (int)date('w', $time);
        $secondsPassed = (in_array($second, $this->seconds) || ($this->seconds == ['*']));
        $minutesPassed = (in_array($minute, $this->minutes) || ($this->minutes == ['*']));
        $hoursPassed   = (in_array($hour, $this->hours) || ($this->hours == ['*']));
        $domPassed     = (in_array($dayOfTheMonth, $this->daysOfTheMonth) || ($this->daysOfTheMonth == ['*']));
        $monthPassed   = (in_array($month, $this->months) || ($this->months == ['*']));
        $dowPassed     = (in_array($dayOfTheWeek, $this->daysOfTheWeek) || ($this->daysOfTheWeek == ['*']));

        if ((!$secondsPassed) && (count($this->seconds) == 1) && is_string($this->seconds[0])) {
            $secondsPassed = (($this->evaluateExpression($this->seconds[0], $second)));
        }
        if ((!$minutesPassed) && (count($this->minutes) == 1) && is_string($this->minutes[0])) {
            $minutesPassed = (($this->evaluateExpression($this->minutes[0], $minute)));
        }
        if ((!$hoursPassed) && (count($this->hours) == 1) && is_string($this->hours[0])) {
            $hoursPassed = (($this->evaluateExpression($this->hours[0], $hour)));
        }
        if ((!$domPassed) && (count($this->daysOfTheMonth) == 1) && is_string($this->daysOfTheMonth[0])) {
            $domPassed = (($this->evaluateExpression($this->daysOfTheMonth[0], $dayOfTheMonth)));
        }
        if ((!$monthPassed) && (count($this->months) == 1) && is_string($this->months[0])) {
            $monthPassed = (($this->evaluateExpression($this->months[0], $month)));
        }
        if ((!$dowPassed) && (count($this->daysOfTheWeek) == 1) && is_string($this->daysOfTheWeek[0])) {
            $dowPassed = (($this->evaluateExpression($this->daysOfTheWeek[0], $dayOfTheWeek)));
        }

        if ($this->hasSeconds()) {
            return (($dowPassed) &&
                ($monthPassed) &&
                ($domPassed) &&
                ($hoursPassed) &&
                ($minutesPassed) &&
                ($secondsPassed));
        } else {
            // Check every minute schedule
            if (($this->schedule == '* * * * *')) {
                return (($this->buffer < 0) || ($second <= $this->buffer));
            // Validate the schedule
            } else {
                return (($dowPassed) &&
                    ($monthPassed) &&
                    ($domPassed) &&
                    ($hoursPassed) &&
                    ($minutesPassed) &&
                    (($this->buffer < 0) || ($second <= $this->buffer)));
            }
        }
    }

    /**
     * To string method
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Determine if the value satisfies the schedule expression
     *
     * @param  string $expression
     * @param  mixed  $value
     * @return bool
     */
    protected function evaluateExpression(string $expression, mixed $value): bool
    {
        if (str_contains($expression, ',')) {
            $values = array_map('trim', explode(',', $expression));
            return in_array($value, $values);
        } else if (str_contains($expression, '/')) {
            $step = (int)substr($expression, (strpos($expression, '/') + 1));
            return (($value % $step) == 0);
        } else if (str_contains($expression, '-')) {
            list($min, $max) = explode('-', $expression);
            return (($value >= $min) && ($value <= $max));
        }

        return false;
    }

}
