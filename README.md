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
* [Configuration Tips](#configuration-tips)

Overview
--------
`pop-queue` is a job queue component that provides the ability to pass an executable job off to a
queue to be processed at a later date and time. Queues can either process jobs or scheduled tasks
via workers. The jobs are stored in an available storage adapter until they are called to executed.
The available storage adapters for the queue component are:

- Database
- Redis
- File

And others can be written as needed, implementing the `AdapterInterface` and extending the `AbstractAdapter`.

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

#### Configure a job and push to the queue

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Processor\Worker;
use Pop\Queue\Processor\Job;

// Create a job
$job1 = Job::create(function() {
    echo 'This is job #1' . PHP_EOL;
});

// Create a worker and add the job to the worker
$worker = Worker::create($job1);

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

If the job is valid, it will run. In this case, it will produce this output:

```text
This is job #1
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

### Completed Jobs

Jobs that run successfully get marked as `completed`. There are a number of methods available
within the queue object to get information on completed jobs:

```php
$queue->hasCompletedJobs();      // bool
$queue->hasCompletedJob($jobId); // bool
$queue->getCompletedJobs();      // array
$queue->getCompletedJob($jobId); // mixed
```

### Failed Jobs

Jobs that run unsuccessfully and fail with an exception get marked as `failed`. There are
a number of methods available within the queue object to get information on failed jobs:

```php
$queue->hasFailedJobs();      // bool
$queue->hasFailedJob($jobId); // bool
$queue->getFailedJobs();      // array
$queue->getFailedJob($jobId); // mixed
```

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
long as they implement `Pop\Queue\Adapter\AdapterInterface`.

### File

The file adapter only requires the location on disk where the queue data will be stored:

```php
use Pop\Queue\Adapter\File;

$adapter = new File(__DIR__ . '/queues'); 
```

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

### Redis

The Redis adapter requires Redis to be correctly configured and running on the server, as well as
the `redis` extension installed with PHP:

```php
use Pop\Queue\Adapter\Redis;

$adapter = new Redis();
```

Once the adapter object is created, it can be passed into the queue object:

```php
use Pop\Queue\Queue;

$queue = Queue::create('pop-queue', $adapter); 
```

[Top](#pop-queue)

Workers
-------

Worker objects server as the owners of the jobs and tasks that are assigned to them.
Once jobs or tasks are registered with a worker object, the worker object can be
added to the queue object and then pushed to the storage adapter.

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
```

### Application Commands

An application command can be registered with a job object as well. You would register
the "route" portion of the command. For example, if the application command route exists:

```bash
$ ./app hello world
```

You would register the command with a job object like this:

```php
use Pop\Queue\Processor\Job;

// Create a job from an application command
$job = Job::command('hello world');
```
### CLI Commands

If the environment is set up to allow executable commands from within PHP, you can
register CLI-based commands with a job object like this:

```php
use Pop\Queue\Processor\Job;

// Create a job from an application command
$job = Job::exec('ls -la');
```

For security reasons, you should exercise caution when using this.

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

[Top](#pop-queue)

Tasks
-----

A task object is an extension of a job object with scheduling capabilities. It has a `Cron`
object and supports a cron-like scheduling format. However, unlike cron, it supports sub-minute
scheduling down to the second.

Here's an example task object where the schedule is set to every 5 minutes:

```php
use Pop\Queue\Processor\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
})->every5Minutes();
```

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

If there is a need for a more custom schedule value, you can schedule that directly:

```php
use Pop\Queue\Processor\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
});

// Submit a cron formatted schedule string
$task->schedule('* */2 1,15 1-4 *')
```

### Run Until

A "run until" value can be set with the task object to give it an "expiration" date:

```php
use Pop\Queue\Processor\Task;

$task = Task::create(function() {
    echo 'This is job #1' . PHP_EOL;
});
$task->every30Minutes()->runUntil('2023-11-30 23:59:59');
```

The `isExpired()` method will evaluate if the job is beyond the "run until" value.
Also, the `isValid()` method will evaluate both the "run until" and max attempts settings.

Configuration Tips
------------------

[Top](#pop-queue)
