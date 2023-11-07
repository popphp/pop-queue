<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Process;

use Pop\Queue\Queue;

/**
 * Task class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2024 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Task extends Job
{

    /**
     * The cron Task
     * @var ?Cron
     */
    protected ?Cron $cron = null;

    /**
     * Max attempts
     * @var int
     */
    protected int $maxAttempts = 0;

    /**
     * Constructor
     *
     * Instantiate the job object
     *
     * @param  mixed   $callable
     * @param  mixed   $params
     * @param  ?string $id
     * @param  Cron    $cron
     */
    public function __construct(mixed $callable = null, mixed $params = null, ?string $id = null, Cron $cron = new Cron())
    {
        parent::__construct($callable, $params, $id);
        $this->setCron($cron);
    }

    /**
     * Create task
     *
     * @param mixed   $callable
     * @param mixed   $params
     * @param ?string $id
     * @param Cron    $cron
     * @return Task
     */
    public static function create(
        mixed $callable = null, mixed $params = null, ?string $id = null, Cron $cron = new Cron()
    ): Task
    {
        return new self($callable, $params, $id, $cron);
    }

    /**
     * Set cron Task
     *
     * @param  Cron $cron
     * @return Task
     */
    public function setCron(Cron $cron): Task
    {
        $this->cron = $cron;
        return $this;
    }

    /**
     * Get cron Task
     *
     * @return ?Cron
     */
    public function getCron(): ?Cron
    {
        return $this->cron;
    }

    /**
     * Get cron Task (alias)
     *
     * @return ?Cron
     */
    public function cron(): ?Cron
    {
        return $this->cron;
    }

    /**
     * Schedule cront
     *
     * @param  $schedule
     * @return Task
     */
    public function schedule(string $schedule): Task
    {
        $this->cron->schedule($schedule);
        return $this;
    }

    /**
     * Set time buffer
     *
     * @param  int $buffer
     * @return Task
     */
    public function setBuffer(int $buffer): Task
    {
        $this->cron->setBuffer($buffer);
        return $this;
    }

    /**
     * Set time buffer (alias)
     *
     * @param  int $buffer
     * @return Task
     */
    public function buffer(int $buffer): Task
    {
        $this->cron->setBuffer($buffer);
        return $this;
    }

    /**
     * Get time buffer
     *
     * @return int
     */
    public function getBuffer(): int
    {
        return $this->cron->getBuffer();
    }

    /**
     * Has cron Task
     *
     * @return bool
     */
    public function hasCron(): bool
    {
        return ($this->cron !== null);
    }

    /**
     * Get seconds
     *
     * @return array
     */
    public function getSeconds(): array
    {
        return $this->cron->getSeconds();
    }

    /**
     * Get minutes
     *
     * @return array
     */
    public function getMinutes(): array
    {
        return $this->cron->getMinutes();
    }

    /**
     * Get hours
     *
     * @return array
     */
    public function getHours(): array
    {
        return $this->cron->getHours();
    }

    /**
     * Get days of the month
     *
     * @return array
     */
    public function getDaysOfTheMonth(): array
    {
        return $this->cron->getDaysOfTheMonth();
    }

    /**
     * Get months
     *
     * @return array
     */
    public function getMonths(): array
    {
        return $this->cron->getMonths();
    }

    /**
     * Get days of the week
     *
     * @return array
     */
    public function getDaysOfTheWeek(): array
    {
        return $this->cron->getDaysOfTheWeek();
    }

    /**
     * Set job schedule to every second
     *
     * @return Task
     */
    public function everySecond(): Task
    {
        $this->cron->everySecond();
        return $this;
    }

    /**
     * Set job schedule to every 5 seconds
     *
     * @return Task
     */
    public function every5Seconds(): Task
    {
        $this->cron->every5Seconds();
        return $this;
    }

    /**
     * Set job schedule to every 10 seconds
     *
     * @return Task
     */
    public function every10Seconds(): Task
    {

        $this->cron->every10Seconds();
        return $this;
    }

    /**
     * Set job schedule to every 15 seconds
     *
     * @return Task
     */
    public function every15Seconds(): Task
    {
        $this->cron->every15Seconds();
        return $this;
    }

    /**
     * Set job schedule to every 20 seconds
     *
     * @return Task
     */
    public function every20Seconds(): Task
    {
        $this->cron->every20Seconds();
        return $this;
    }

    /**
     * Set job schedule to every 30 seconds
     *
     * @return Task
     */
    public function every30Seconds(): Task
    {
        $this->cron->every30Seconds();
        return $this;
    }

    /**
     * Set job schedule to by specific seconds
     *
     * @param  mixed $seconds
     * @return Task
     */
    public function seconds(mixed $seconds): Task
    {
        $this->cron->seconds($seconds);
        return $this;
    }

    /**
     * Set job schedule to every minute
     *
     * @return Task
     */
    public function everyMinute(): Task
    {
        $this->cron->everyMinute();
        return $this;
    }

    /**
     * Set job schedule to every 5 minutes
     *
     * @return Task
     */
    public function every5Minutes(): Task
    {
        $this->cron->every5Minutes();
        return $this;
    }

    /**
     * Set job schedule to every 10 minutes
     *
     * @return Task
     */
    public function every10Minutes(): Task
    {
        $this->cron->every10Minutes();
        return $this;
    }

    /**
     * Set job schedule to every 15 minutes
     *
     * @return Task
     */
    public function every15Minutes(): Task
    {
        $this->cron->every15Minutes();
        return $this;
    }

    /**
     * Set job schedule to every 20 minutes
     *
     * @return Task
     */
    public function every20Minutes(): Task
    {
        $this->cron->every20Minutes();
        return $this;
    }

    /**
     * Set job schedule to every 30 minutes
     *
     * @return Task
     */
    public function every30Minutes(): Task
    {
        $this->cron->every30Minutes();
        return $this;
    }

    /**
     * Set job schedule to by specific minutes
     *
     * @param  mixed $minutes
     * @return Task
     */
    public function minutes(mixed $minutes): Task
    {
        $this->cron->minutes($minutes);
        return $this;
    }

    /**
     * Set job schedule to by specific hours
     *
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Task
     */
    public function hours(mixed $hours, mixed $minutes = null): Task
    {
        $this->cron->hours($hours, $minutes);
        return $this;
    }

    /**
     * Set job schedule to hourly
     *
     * @param  mixed $minutes
     * @return Task
     */
    public function hourly(mixed $minutes = null): Task
    {
        $this->cron->hourly($minutes);
        return $this;
    }

    /**
     * Set job schedule to daily
     *
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Task
     */
    public function daily(mixed $hours, mixed $minutes = null): Task
    {
        $this->cron->daily($hours, $minutes);
        return $this;
    }

    /**
     * Set job schedule to daily at specific time, i.e. 14:30
     *
     * @param  string $time
     * @return Task
     */
    public function dailyAt(string $time): Task
    {
        $this->cron->dailyAt($time);
        return $this;
    }

    /**
     * Set job schedule to weekly
     *
     * @param  mixed $day
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Task
     */
    public function weekly(mixed $day, mixed $hours = null, mixed $minutes = null): Task
    {
        $this->cron->weekly($day, $hours, $minutes);
        return $this;
    }

    /**
     * Set job schedule to monthly
     *
     * @param  mixed $day
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Task
     */
    public function monthly(mixed $day, mixed $hours = null, mixed $minutes = null): Task
    {
        $this->cron->monthly($day, $hours, $minutes);
        return $this;
    }

    /**
     * Set job schedule to quarterly
     *
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Task
     */
    public function quarterly(mixed $hours = null, mixed $minutes = null): Task
    {
        $this->cron->quarterly($hours, $minutes);
        return $this;
    }

    /**
     * Set job schedule to yearly
     *
     * @param  bool $endOfYear
     * @param  mixed $hours
     * @param  mixed $minutes
     * @return Task
     */
    public function yearly(bool $endOfYear = false, mixed $hours = null, mixed $minutes = null): Task
    {
        $this->cron->yearly($endOfYear, $hours, $minutes);
        return $this;
    }

    /**
     * Set job schedule to weekdays
     *
     * @return Task
     */
    public function weekdays(): Task
    {
        $this->cron->weekdays();
        return $this;
    }

    /**
     * Set job schedule to weekends
     *
     * @return Task
     */
    public function weekends(): Task
    {
        $this->cron->weekends();
        return $this;
    }

    /**
     * Set job schedule to Sundays
     *
     * @return Task
     */
    public function sundays(): Task
    {
        $this->cron->sundays();
        return $this;
    }

    /**
     * Set job schedule to Mondays
     *
     * @return Task
     */
    public function mondays(): Task
    {
        $this->cron->mondays();
        return $this;
    }

    /**
     * Set job schedule to Tuesdays
     *
     * @return Task
     */
    public function tuesdays(): Task
    {
        $this->cron->tuesdays();
        return $this;
    }

    /**
     * Set job schedule to Wednesdays
     *
     * @return Task
     */
    public function wednesdays(): Task
    {
        $this->cron->wednesdays();
        return $this;
    }

    /**
     * Set job schedule to Thursdays
     *
     * @return Task
     */
    public function thursdays(): Task
    {
        $this->cron->thursdays();
        return $this;
    }

    /**
     * Set job schedule to Fridays
     *
     * @return Task
     */
    public function fridays(): Task
    {
        $this->cron->fridays();
        return $this;
    }

    /**
     * Set job schedule to Saturdays
     *
     * @return Task
     */
    public function saturdays(): Task
    {
        $this->cron->saturdays();
        return $this;
    }

    /**
     * Set job schedule to between two hours
     *
     * @param  int $start
     * @param  int $end
     * @return Task
     */
    public function between(int $start, int $end): Task
    {
        $this->cron->between($start, $end);
        return $this;
    }

}