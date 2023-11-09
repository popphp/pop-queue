pop-queue
=========

[![Build Status](https://github.com/popphp/pop-queue/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-queue/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-queue)](http://cc.popphp.org/pop-queue/)

[![Join the chat at https://popphp.slack.com](https://media.popphp.org/img/slack.svg)](https://popphp.slack.com)
[![Join the chat at https://discord.gg/D9JBxPa5](https://media.popphp.org/img/discord.svg)](https://discord.gg/D9JBxPa5)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
* [Jobs](#jobs)
    - [Callables](#callables)
    - [Application Commands](#application-commands)
    - [CLI Commands](#cli-commands)
    - [Attempts](#attempts)
* [Tasks](#tasks)
    - [Scheduling](#scheduling)
    - [Run Until](#run-until)
    - [Buffer](#buffer)
* [Adapters](#adapters)
    - [File](#file)
    - [Database](#database)
    - [Redis](#redis)
* [Queues](#queues)
    - [Completed Jobs](#completed-jobs)
    - [Failed Jobs](#failed-jobs)
    - [Clear Queue](#clear-queue)
* [Workers](#workers)
* [Configuration](#configuration)

Overview
--------
`pop-queue` is a job queue component that provides the ability to pass executable jobs or tasks
off to a queue to be processed at a later date and time. Queues can either process jobs or scheduled
tasks. The jobs or tasks are stored with an available queue storage adapter until they are called to be
executed. The available storage adapters for the queue component are:

- Redis
- Database
- File
- AWS SQS*

The difference between jobs and tasks are that jobs are "one and done" (unless they fail) and pop off
the queue once complete. Tasks are persistent and remain in the queue to run repeatedly on their set
schedule, or until they expire.

*\* - The SQS adapter does not support tasks.*

`pop-queue` is a component of the [Pop PHP Framework](http://www.popphp.org/).

[Top](#pop-queue)

Install
-------

Install `pop-queue` using Composer.

    composer require popphp/pop-queue

Or, require it in your composer.json file

    "require": {
        "popphp/pop-queue" : "^2.0.0"
    }

[Top](#pop-queue)

Quickstart
----------

#### Create a job and push to the queue

Simply adding a job to the queue will push it to the queue storage adapter.

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;

// Create a job and add it to a queue
$job = Job::create(function() {
    echo 'This is job' . PHP_EOL;
});

$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->addJob($job);
```

#### Call the queue to process the job

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

// Call up the queue and pass it to a worker object
$queue  = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$worker = Worker::create($queue);

// Trigger the worker to work the next job across its queues
$worker->workAll();
```

If the job is valid, it will run. In this case, it will produce this output:

```text
This is a job
```

#### Create a scheduled task and push to the queue

Create a task, set the schedule and add it to the queue.

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Task;

$task = Task::create(function() {
    echo 'This is a scheduled task' . PHP_EOL;
})->every30Minutes();

// Add to a queue
$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->addTask($task);
```

#### Call the queue to run the scheduled task

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

// Call up the queue and pass it to a worker object
$queue  = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$worker = Worker::create($queue);

// Trigger the worker to run the next scheduled task across its queues
$worker->runAll();
```

If the task is valid, it will run. In this case, it will produce this output:

```text
This is a scheduled task
```

[Top](#pop-queue)

Jobs
----

Job objects are at the heart of the `pop-queue` component. They are objects that can execute
either a callable, an application command or even a CLI-based command (if the environment is
set up to allow that.)

Jobs get assigned an ID hash by default for reference.

```php
var_dump($job->hasJobId());
$id = $job->getJobId();
```

As a job is picked up to be executed, there are a number of methods to assist with the
status of a job during its lifecycle:

```php
var_dump($job->hasStarted()); // Has a started timestamp
var_dump($job->hasNotRun());  // No started timestamp and no completed timestamp
var_dump($job->isRunning());  // Has a started timestamp, but not a completed/failed
var_dump($job->isComplete()); // Has a completed timestamp
var_dump($job->hasFailed());  // Has a failed timestamp
var_dump($job->getStarted());
var_dump($job->getCompleted());
var_dump($job->getFailed());
```

[Top](#pop-queue)

### Callables

Any callable object can be passed into a job object:

```php
use Pop\Queue\Process\Job;

// Create a job from a closure
$job1 = Job::create(function() {
    echo 'This is job #1' . PHP_EOL;
});

// Create a job from a static class method
$job2 = Job::create('MyApp\Service\SomeService::doSomething');

// Create a job with parameters
$closure = function($num) {
    echo 'This is job ' . $num . PHP_EOL;
};

// The second argument passed is the callable's parameter(s) 
$job3 = Job::create($closure, 1);
```

If the callable needs access to the main application object, you can pass that to the
queue object, and it will be prepended to the parameters of the callable object:

```php
use Pop\Queue\Queue;
use Pop\Queue\Worker;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;

// Create a job that needs the application object
$job = Job::create(function($application) {
    // Do something with the application
});

$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->addJob($job);
```

Once the callable is added to the queue, the worker will need to be aware of the application
object in order to pass it down to the job:

```php
use Pop\Queue\Queue;
use Pop\Queue\Worker;
use Pop\Queue\Adapter\File;
use Pop\Application;

$application = new Application();

$queue  = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$worker = Worker::create($queue, $application);

// When the worker works the job, it will push the application object to the job
$worker->workAll();
```

[Top](#pop-queue)

### Application Commands

An application command can be registered with a job object as well. You would register
the "route" portion of the command. For example, if the following application command
route exists:

```bash
$ ./app hello world
```

You would register the command with a job object like this:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;

// Create a job from an application command and add to the queue
$job   = Job::command('hello world');
$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->addJob($job);
```

Again, the worker object would need to be aware of the application object to push down to
the job object that requires it:

```php
$queue  = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$worker = Worker::create($queue, $application);
```

[Top](#pop-queue)

### CLI Commands

If the environment is set up to allow executable commands from within PHP, you can
register CLI-based commands with a job object like this:

```php
use Pop\Queue\Process\Job;

// Create a job from an executable CLI command
$job = Job::exec('ls -la');
```

For security reasons, you should exercise caution when using this.

[Top](#pop-queue)

### Attempts

By default, a job runs only once. However, if a job fails, it will be pushed back onto
the queue. But you can limit how much that happens by setting the max attempts of a job.

```php
use Pop\Queue\Process\Job;

$job = Job::create(function() {
    echo 'This is job #1' . PHP_EOL;
});
$job->setMaxAttempts(10);
```

If you want the job to never unregister and keep trying to execute after failure, you can
set the max attempts to `0`:

```php
$job->setMaxAttempts(0);
```

And you can check the number of attempts vs. the max attempts like this:

```php
var_dump($job->hasExceededMaxAttempts());
```

The `isValid()` method is also available and checks both the max attempts and the
"run until" setting (which is used more with task objects - see below.)

**NOTE:** The [run until](#run-until) can be enforced on a non-scheduled job and the
[max attempts](#attempts) can be enforced on a scheduled task.

[Top](#pop-queue)

Tasks
-----

A task object is an extension of a job object with scheduling capabilities. It has a `Cron`
object and supports a cron-like scheduling format. However, unlike cron, it can also support
sub-minute scheduling down to the second.

Here's an example task object where the schedule is set to every 5 minutes:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Task;

// Create a scheduled task and add to the queue
$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
})->every5Minutes();

$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->addTask($task);
```

[Top](#pop-queue)

### Scheduling

Here is a list of available methods to assist with setting common schedules:

- `everySecond()`
- `every5Seconds()`
- `every10Seconds()`
- `every15Seconds()`
- `every20Seconds()`
- `every30Seconds()`
- `seconds(mixed $seconds)`
- `everyMinute()`
- `every5Minutes()`
- `every10Minutes()`
- `every15Minutes()`
- `every20Minutes()`
- `every30Minutes()`
- `minutes(mixed $minutes)`
- `hours(mixed $hours, mixed $minutes = null)`
- `hourly(mixed $minutes = null)`
- `daily(mixed $hours, mixed $minutes = null)`
- `dailyAt(string $time)`
- `weekly(mixed $day, mixed $hours = null, mixed $minutes = null)`
- `monthly(mixed $day, mixed $hours = null, mixed $minutes = null)`
- `quarterly(mixed $hours = null, mixed $minutes = null)`
- `yearly(bool $endOfYear = false, mixed $hours = null, mixed $minutes = null)`
- `weekdays()`
- `weekends()`
- `sundays()`
- `mondays()`
- `tuesdays()`
- `wednesdays()`
- `thursdays()`
- `fridays()`
- `saturdays()`
- `between(int $start, int $end)`

If there is a need for a more custom schedule value, you can schedule that directly with a
cron-formatted string:

```php
use Pop\Queue\Process\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
});

// Submit a cron-formatted schedule string
$task->schedule('* */2 1,15 1-4 *')
```

Or, you can use the non-standard format to prepend a "seconds" value to the string:

```php
// Submit a non-standard cron-formatted schedule string
// that includes a prepended "seconds" value 
$task->schedule('*/10 * */2 1,15 1-4 *')
```

The standard cron string supports 5 values for

- Minutes
- Hours
- Days of the month
- Months
- Days of the week

in the format of:

```text
min  hour  dom  month  dow
 *    *     *     *     *
```

To keep with that format and support a non-standard "seconds" value,
that value is prepended to the string creating 6 values:

```text     
sec  min  hour  dom  month  dow
 *    *    *     *     *     *
```

If a task is schedule using seconds, it will trigger the worker to process the task
at the sub-minute level.

[Top](#pop-queue)

### Run Until

By default, a task is set to an unlimited number of attempts and is expected to continue
to execute at its scheduled time. However, a "run until" value can be set with the task
object to give it an "expiration" date:

```php
use Pop\Queue\Process\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
});
// Using a valid date/time string
$task->every30Minutes()->runUntil('2023-11-30 23:59:59');
```

It can also accept a timestamp:

```php
// Using a valid UNIX timestamp
$task->every30Minutes()->runUntil(1701410399);
```

The `isExpired()` method will evaluate if the job is beyond the "run until" value.
Also, the `isValid()` method will evaluate both the "run until" and max attempts settings.

**NOTE:** The [run until](#run-until) can be enforced on a non-scheduled job and the
[max attempts](#attempts) can be enforced on a scheduled task.

[Top](#pop-queue)

### Buffer

By default, a scheduled task's time evaluation is strict, which in most cases means that the
execution time will happen on the `00` second of the timestamp. If, for some reason, there is
a concern or possibility that the execution of a task would be delayed - and not be evaluated
on a `00` second timestamp - you can set a time buffer to "soften" the strictness of the
scheduled time evaluation.

The below example gives a 10 second "cushion" to ensure that if there were any processing delay,
the task's scheduled time evaluation should evaluate to `true` in the window of 0-10 seconds of the
evaluated timestamp.

```php
use Pop\Queue\Process\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
});
$task->every30Minutes()
$task->setBuffer(10);
```

If you want to set it so that the task runs no matter what, as long as the evaluated timestamp
is at or past the scheduled time, you can set the buffer to `-1`:

```php
use Pop\Queue\Process\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
});
$task->every30Minutes()
$task->setBuffer(-1);
```

[Top](#pop-queue)

Configuration
-------------

If you have a CLI application that is aware of your queues and has access to them, you can
use that application to be the "manager" of your queues, checking them and processing them
as needed. Assuming you have a CLI application that processes the queue via a command like:

```bash
$ ./app manage queue
```

You could set up a cron job to trigger this application every minute:

```bash
* * * * * cd /path/to/your/project && ./app manage queue
```

Or, if you'd like any output to be routed to `/dev/null`:

```bash
* * * * * cd /path/to/your/project && ./app manage queue >> /dev/null 2>&1
```

[Top](#pop-queue)

