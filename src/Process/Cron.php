<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
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
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
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
     * Grace period, in seconds, allowed between the scheduled time and the
     * evaluation of it. Defaults to -1, i.e. the seconds value is disregarded
     * and the schedule is due for the whole of its matching minute.
     * @var int
     */
    protected int $gracePeriod = -1;

    /**
     * Constructor
     *
     * Instantiate the cron  object
     *
     * @param  ?string $schedule
     * @param  int     $gracePeriod
     */
    public function __construct(?string $schedule = null, int $gracePeriod = -1)
    {
        if ($schedule !== null) {
            $this->schedule($schedule);
        }
        $this->setGracePeriod($gracePeriod);
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
     * Set grace period
     *
     * @param  int $gracePeriod
     * @return Cron
     */
    public function setGracePeriod(int $gracePeriod): Cron
    {
        $this->gracePeriod = $gracePeriod;
        return $this;
    }

    /**
     * Get grace period
     *
     * @return int
     */
    public function getGracePeriod(): int
    {
        return $this->gracePeriod;
    }

    /**
     * Has grace period
     *
     * Only a grace period of exactly 0 - strict evaluation to the 00 second -
     * grants no grace. A negative value is the loosest setting there is, not
     * the absence of one.
     *
     * @return bool
     */
    public function hasGracePeriod(): bool
    {
        return ($this->gracePeriod !== 0);
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
     * The grace period governs how late an evaluation may be and still count
     * as due. It applies only to minute-granularity schedules; a schedule with
     * a seconds field is always evaluated exactly.
     *
     * $gracePeriod = -1;    disregards the seconds value - due for the whole
     *                       of the matching minute (the default)
     * $gracePeriod = 1-59;  due within that many seconds of the scheduled time
     * $gracePeriod = 0;     strict evaluation to the 00 second
     *
     * Note that a missed window is not made up later - there is no catch-up.
     *
     * @param  mixed $time
     * @param  ?int  $gracePeriod
     * @throws Exception
     * @return bool
     */
    public function evaluate(mixed $time = null, ?int $gracePeriod = null): bool
    {
        if ($time === null) {
            $time = time();
        } else if (is_string($time)) {
            $time = strtotime($time);
            if ($time === false) {
                throw new Exception('Error: That time value is not valid.');
            }
        }

        if ($gracePeriod !== null) {
            $this->setGracePeriod($gracePeriod);
        }

        // One getdate() rather than six separate date() calls: it returns every
        // field this method needs from a single timestamp conversion. Queue::run()
        // calls this once per task per second inside its sub-minute tick loop, so
        // this is the hottest path in the component and the six-fold saving lands
        // squarely on it.
        $parts  = getdate($time);
        $second = $parts['seconds'];

        // Short-circuited, coarsest field first. The result is an AND across every
        // field, so stopping at the first failure cannot change the answer - and
        // the coarse fields (day-of-week, month, day-of-month) are the ones that
        // rule a schedule out most often, so testing them first means a task that
        // isn't due today costs one field test instead of six.
        //
        // The seconds field is deliberately excluded from this chain: on a
        // minute-granularity schedule it is not part of the decision at all (the
        // grace period governs the seconds instead), which is why hasSeconds()
        // gates it below rather than it being tested inline here.
        if ((!$this->fieldPasses($this->daysOfTheWeek, $parts['wday'])) ||
            (!$this->fieldPasses($this->months, $parts['mon'])) ||
            (!$this->fieldPasses($this->daysOfTheMonth, $parts['mday'])) ||
            (!$this->fieldPasses($this->hours, $parts['hours'])) ||
            (!$this->fieldPasses($this->minutes, $parts['minutes']))) {
            return false;
        }

        if ($this->hasSeconds()) {
            return $this->fieldPasses($this->seconds, $second);
        }

        // Reached only when every minute-granularity field has already matched,
        // which is exactly the condition the old explicit '* * * * *' special case
        // tested for - an all-wildcard schedule passes every field above, so it
        // arrives here and gets the same grace-period-only answer it always did,
        // without needing its own branch.
        return (($this->gracePeriod < 0) || ($second <= $this->gracePeriod));
    }

    /**
     * Determine whether one schedule field matches the corresponding value from
     * the time being evaluated.
     *
     * This is the per-field test that evaluate() used to inline six times over.
     * The three cases, in the order the original checked them: the wildcard '*',
     * a literal value present in the field, and - only for a single-element
     * string field - a compound expression (a comma list, a step, or a range)
     * handed off to evaluateExpression().
     *
     * The loose in_array() comparison is intentional and load-bearing: field
     * values arrive as strings when parsed out of a schedule string but as ints
     * when set through the fluent helpers (hourly(), daily(), and friends all
     * assign ints), so both have to compare equal to the int taken from the
     * timestamp.
     *
     * @param  array $field
     * @param  int   $value
     * @return bool
     */
    protected function fieldPasses(array $field, int $value): bool
    {
        // Checked before in_array() rather than after it, as the original did:
        // the answer is identical either way (no integer is loosely equal to
        // '*'), and the wildcard is overwhelmingly the most common field, so
        // it is the one worth answering first.
        if ($field == ['*']) {
            return true;
        }

        if (in_array($value, $field)) {
            return true;
        }

        return ((count($field) == 1) && is_string($field[0]) && $this->evaluateExpression($field[0], $value));
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
