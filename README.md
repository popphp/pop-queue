pop-queue
=========

[![Build Status](https://github.com/popphp/pop-queue/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-queue/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-queue)](http://cc.popphp.org/pop-queue/)

[![Join the chat at https://popphp.slack.com](https://media.popphp.org/img/slack.svg)](https://popphp.slack.com)
[![Join the chat at https://discord.gg/D9JBxPa5](https://media.popphp.org/img/discord.svg)](https://discord.gg/D9JBxPa5)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
* [Queues](#queues)
    - [Completed Jobs](#completed-jobs)
    - [Failed Jobs](#failed-jobs)
    - [Clear Queue](#clear-queue)
* [Manager](#manager)
* [Adapters](#adapters)
    - [File](#file)
    - [Database](#database)
    - [Redis](#redis)
* [Workers](#workers)
* [Jobs](#jobs)
    - [Callables](#callables)
    - [Application Commands](#application-commands)
    - [CLI Commands](#cli-commands)
    - [Attempts](#attempts)
* [Tasks](#tasks)
    - [Scheduling](#scheduling)
    - [Run Until](#run-until)
    - [Buffer](#buffer)
* [Configuration](#configuration)

Overview
--------
`pop-queue` is a job queue component that provides the ability to pass an executable job off to a
queue to be processed at a later date and time. Queues can either process jobs or scheduled tasks
via workers. The jobs are stored in an available storage adapter until they are called to be executed.
The available storage adapters for the queue component are:

- Database
- Redis
- File

Others can be written as needed, implementing the `Pop\Queue\Adapter\AdapterInterface` and extending
the `Pop\Queue\Adapter\AbstractAdapter`.

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

#### Create a job and a task and push to the queue

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Processor\Worker;
use Pop\Queue\Processor\Job;
use Pop\Queue\Processor\Task;

// Create a job
$job = Job::create(function() {
    echo 'This is job' . PHP_EOL;
});

// Create a scheduled task
$task = Task::create(function() {
    echo 'This is a scheduled task' . PHP_EOL;
})->every30Minutes();

// Create a worker and add the job and task to the worker
$worker = new Worker();
$worker->addJob($job)
    ->addTask($task);

// Create the queue object, add the worker and push to the queue
$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->addWorker($worker);
$queue->pushAll();
```

#### Call the queue to process the job

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

// Call up the queue object and process all valid jobs 
$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->processAll(); 
```

If the job and task are valid, they will run. In this case, it will produce this output:

```text
This is a job
This is scheduled task
```

[Top](#pop-queue)

Queues
------

As shown in the [quickstart](#quickstart) example above, the queue object utilizes worker objects as
owners of the jobs and tasks assigned to them. The jobs are stored with the selected storage
adapter. You can assign multiple jobs or tasks to a worker. And you can assign multiple workers
to a queue. The basic idea is that you can define your jobs or tasks and pass those to the
worker or workers. Then register the workers with the queue and "push" them to the storage to
be stored for execution at a later time.

For reference, queues have a name, which is passed to the constructor, along with
the storage adapter object and an optional application object.

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'), $application);
```

A `Pop\Application` object can be passed to the queue should any of the jobs' or tasks' callable objects
need it. Or, an application command can be directly set as the job or task callable, so the application
object would be needed then as well. (More on working with a [application commands](#application-commands) below.) 

[Top](#pop-queue)

### Completed Jobs

Jobs that run successfully get marked as `completed`. There are a number of methods available
within the queue object to get information on completed jobs:

```php
$queue->hasCompletedJobs();      // bool
$queue->hasCompletedJob($jobId); // bool
$queue->getCompletedJobs();      // array
$queue->getCompletedJob($jobId); // mixed
```

[Top](#pop-queue)

### Failed Jobs

Jobs that run unsuccessfully and fail with an exception get marked as `failed`. There are
a number of methods available within the queue object to get information on failed jobs:

```php
$queue->hasFailedJobs();      // bool
$queue->hasFailedJob($jobId); // bool
$queue->getFailedJobs();      // array
$queue->getFailedJob($jobId); // mixed
```

[Top](#pop-queue)

### Clear Queue
                     
The queue can be cleared out in a number of ways.

**Clearing the Queue**

```php
$queue->clearFailed();            // Clears only the failed jobs within the queue namespace
$queue->clear(bool $all = false); // Clears all jobs within the queue namespace
```

**Flushing the Queue**

```php
$queue->flushFailed(); // Clears only the failed jobs across all possible queue namespaces
$queue->flush();       // Clears jobs across all possible queue namespaces
$queue->flushAll();    // Clears everything across all possible queue namespaces
```

[Top](#pop-queue)

Manager
-------

The manager object provides a way to manage multiple queues at the same time. You can add
queues to it and call them up at a later time:

##### Add queues to a manager object

```php
use Pop\Queue\Manager;
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

$adapter = new File(__DIR__ . '/queue');

$queue1 = new Queue('pop-queue1', $adapter);
$queue2 = new Queue('pop-queue2', $adapter);
$queue3 = new Queue('pop-queue3', $adapter);

$manager = Manager::create([$queue1, $queue2, $queue3]);
```

##### Load pre-existing queues into a manager object

If it's known that a queue exists containing jobs within a particular storage object,
you can load those pre-existing queues like this:

```php
use Pop\Queue\Manager;
use Pop\Queue\Adapter\File;

$adapter = new File(__DIR__ . '/queue');
$manager = Manager::load($adapter);
```

Following, the above example where 3 separate queues were created with the file adapter,
the manager would now be loaded with those three queues, if they still exist and contain
jobs. However, if a queue is empty or no queues are registered with that adapter, then
no queues will be loaded into the manager.

[Top](#pop-queue)

Adapters
--------

By default, there are three available adapters, but additional ones could be created as
long as they implement `Pop\Queue\Adapter\AdapterInterface` and extend
`Pop\Queue\Adapter\AbstractAdapter`.

### File

The file adapter only requires the location on disk where the queues will be stored:

```php
use Pop\Queue\Adapter\File;

$adapter = new File(__DIR__ . '/queues'); 
```

[Top](#pop-queue)

### Database

The database adapter requires the use of the `pop-db` component and a database adapter
from that component:

```php
use Pop\Queue\Adapter\Database;
use Pop\Db\Db;

$db = Db::mysqlConnect([
    'database' => 'DATABASE',
    'username' => 'DB_USER',
    'password' => 'DB_PASS'
]);

$adapter = new Database($db); 
```

Two tables are utilized in the database to manage the jobs. If they do not exist, they
will be automatically created. By default, they are named `pop_queue_jobs` and
`pop_queue_failed_jobs`. If you would like to name them something else, you can pass
those names into the constructor:

```php
$adapter = new Database($db, 'my_jobs', 'my_failed_jobs'); 
```

[Top](#pop-queue)

### Redis

The Redis adapter requires Redis to be correctly configured and running on the server, as well as
the `redis` extension installed with PHP:

```php
use Pop\Queue\Adapter\Redis;

$adapter = new Redis();
```

The Redis adapter uses `localhost` and port `6379` as defaults. It also manages the jobs with the
Redis server by means of a key prefix. By default, that prefix is set to `pop-queue-`. If you would
like to use alternate values for any these, you can pass them into the constructor:

```php
$adapter = new Redis('my.redis.server.com', 6380, 'my-queue');
```

Once the adapter object is created, it can be passed into the queue object:

```php
use Pop\Queue\Queue;

$queue = Queue::create('pop-queue', $adapter); 
```

[Top](#pop-queue)

Workers
-------

Worker objects serve as the owners of the jobs and tasks that are assigned to them.
Once jobs or tasks are registered with a worker object, the worker object can be
added to the queue object and then their jobs can be pushed to the storage adapter.

```php
use Pop\Queue\Processor\Worker;
use Pop\Queue\Processor\Job;

// Create a job
$job1 = Job::create(function() {
    echo 'This is job #1' . PHP_EOL;
});

// Create a worker and add the job to the worker
$worker = Worker::create($job1);
```

The worker object has a number of methods to assist in managing jobs and tasks:

- `addJob(AbstractJob $job, ?int $maxAttempts = null)`
- `addJobs(array $jobs, ?int $maxAttempts = null)`
- `addTask(Task $task, ?int $maxAttempts = 0)`
- `addTasks(array $tasks, ?int $maxAttempts = null)`
- `getJobs()`
- `getJob(int $index)`
- `hasJobs()`
- `hasJob(int $index)`

If any jobs return any results, those can be accessed like this:

- `getJobResults()`
- `getJobResult(mixed $index)`
- `hasJobResults()`

You can access completed jobs within the worker object like this:

- `getCompletedJobs()`
- `getCompletedJob(mixed $index)`
- `hasCompletedJobs()`

You can access failed jobs anf their exceptions within the worker object like this:

- `getFailedJobs()`
- `getFailedJob(mixed $index)`
- `hasFailedJobs()`
- `getFailedExceptions()`
- `getFailedException($index)`
- `hasFailedExceptions()`


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

As a job is picked up by a worker object to be executed, there are a number of methods to
assist with the status of a job:

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
use Pop\Queue\Processor\Job;

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
queue object and it will be added to the parameters of the callable object:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Processor\Worker;
use Pop\Queue\Processor\Job;

// Create a job that needs the application object
$job = Job::create(function($application) {
    // Do something with the application
});

// Create a worker and add the job and task to the worker
$worker = new Worker::create($job);

// Create the queue object, add the worker and push to the queue
$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'), $application);
$queue->addWorker($worker);
$queue->pushAll();
```

When the time comes to execute the job, the application object can be passed into
the queue object. From there, the application object will get passed down and prepended
to the callable object's parameters:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

$adapter = new File(__DIR__ . '/queue');
$queue   = Queue::load('pop-queue', $adapter, $application);
$queue->processAll();
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
use Pop\Queue\Processor\Job;

// Create a job from an application command
$job = Job::command('hello world');
```

[Top](#pop-queue)

### CLI Commands

If the environment is set up to allow executable commands from within PHP, you can
register CLI-based commands with a job object like this:

```php
use Pop\Queue\Processor\Job;

// Create a job from an executable CLI command
$job = Job::exec('ls -la');
```

For security reasons, you should exercise caution when using this.

[Top](#pop-queue)

### Attempts

By default, a job is configured to run only once. However, if you need the job to stay
registered with the worker and run more than once, you can control that by setting the
max attempts.

```php
use Pop\Queue\Processor\Job;

$job = Job::create(function() {
    echo 'This is job #1' . PHP_EOL;
});
$job->setMaxAttempts(10);
var_dump($job->isAttemptOnce()); // Returns false
```

If you want the job to never unregister and execute everytime the queue processes the
worker object, you can set the max attempts to `0`:

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
object and supports a cron-like scheduling format. However, unlike cron, it can also supports
sub-minute scheduling down to the second.

Here's an example task object where the schedule is set to every 5 minutes:

```php
use Pop\Queue\Processor\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
})->every5Minutes();
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
use Pop\Queue\Processor\Task;

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
use Pop\Queue\Processor\Task;

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
use Pop\Queue\Processor\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
});
$task->every30Minutes()
$task->setBuffer(10);
```

If you want to set it so that the task runs no matter what, as long as the evaluated timestamp
is at or past the scheduled time, you can set the buffer to `-1`:

```php
use Pop\Queue\Processor\Task;

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

