pop-queue
=========

[![Build Status](https://github.com/popphp/pop-queue/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-queue/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-queue)](http://cc.popphp.org/pop-queue/)

[![Join the chat at https://discord.gg/TZjgT74U7E](https://media.popphp.org/img/discord.svg)](https://discord.gg/TZjgT74U7E)

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
    - [Redis](#redis)
    - [Database](#database)
    - [File](#file)
    - [Memory](#memory)
    - [AWS SQS](#aws-sqs)
* [Queues](#queues)
    - [Priority](#priority)
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

`pop-queue` is a component of the [Pop PHP Framework](https://www.popphp.org/).

[Top](#pop-queue)

Install
-------

Install `pop-queue` using Composer.

    composer require popphp/pop-queue

Or, require it in your composer.json file

    "require": {
        "popphp/pop-queue" : "^2.1.3"
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

A job can also set a retry backoff, so a failed retry isn't attempted immediately:

```php
use Pop\Queue\Process\Job;

$job = Job::create(function() {
    echo 'This is job #1' . PHP_EOL;
});

// Wait 30 seconds before every retry
$job->setBackoff(30);

// Or a per-attempt schedule: 10s after the 1st failure, 30s after the 2nd,
// 60s after the 3rd and every failure after that
$job->setBackoff([10, 30, 60]);
```

**NOTE:** As of the atomic-adapter rewrite, `setBackoff()`'s delay is honored by `Memory`, `File`,
`Database`, and `Redis` — a failed job with a backoff set won't be retried until the delay elapses,
on any of those four. `AWS SQS` is the one exception: its `release()` deletes and re-sends the
message without recomputing a delay, so a backed-off job on that adapter retries immediately
regardless of `setBackoff()`.

A job (or task) can also be dispatched with a delay, so it isn't eligible to run until later:

```php
// Available in 60 seconds
$job->delay(60);

// Or at an absolute time
$job->delay('2026-12-01 09:00:00');
```

Unlike `setBackoff()`, an initial `delay()` set before a job is first pushed *is* honored by every
adapter. On `Memory`, `File`, `Database` and `Redis`, `reserve()` skips over a job that isn't
available yet and hands back the next one that is. On `AWS SQS` the delay is enforced by SQS
itself: `push()` translates it into the message's `DelaySeconds`, which AWS caps at 900 seconds
(15 minutes) — a longer delay on that adapter is clamped to that maximum. **Exception:** AWS
doesn't allow per-message `DelaySeconds` on a FIFO SQS queue (it's a queue-level setting there
instead), so on a `.fifo` queue a job's `delay()` is not applied — the job becomes available
immediately, same as if no delay were set.

And a job can set a soft execution timeout, enforced when the `pcntl` extension is available:

```php
// Interrupt the job if it runs longer than 30 seconds
$job->setTimeout(30);
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

When multiple workers share the same adapter storage (e.g. several server processes each invoking the
scheduler around the same moment), `run()` claims each due task before executing it, so only one worker
actually runs a given task for a given due-window - the others silently skip it. This is best-effort
deduplication via shared storage, not distributed consensus: workers whose clocks disagree by more than a
few seconds could still both run the same task. If a task's side effects can't tolerate that residual risk,
make the task itself idempotent.

A claim persists for up to 90 seconds if the task it's guarding never completes (it's never refreshed or
explicitly released) - long enough to safely cover a coarse (minute-granularity) task's full due-window.

Note: adding `claimTaskRun()` to `TaskAdapterInterface` is a breaking change for any third-party adapter
implementing that interface directly - they must now implement this method too.

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

Adapters
--------

By default, there are five available adapters, but additional ones could be created as long as they
implement `Pop\Queue\Adapter\AdapterInterface` and extend `Pop\Queue\Adapter\AbstractAdapter`.

A job that fails and still has attempts remaining is retried (after any backoff delay on
`Memory`/`File`/`Database`/`Redis`, immediately regardless of backoff on `AWS SQS` — see
[Attempts](#attempts) above); a job
that exhausts its attempts is buried instead of being silently dropped. On `Memory`, `Redis`,
`Database` and `File`, a buried job is moved to that adapter's own dead-letter store, where it can
be inspected and recovered — see each adapter's `getDeadJobs()`/`getDeadJob()`/`retryDeadJob()`/
`deleteDeadJob()`. **`AWS SQS` is the exception:** it has no client-side dead-letter store, so
burying a job there simply deletes the message (`getDeadJobs()` returns an empty array,
`getDeadJob()` returns `null`, and `retryDeadJob()`/`deleteDeadJob()` throw). For real dead-letter
handling on SQS, configure a native SQS redrive policy pointing at a separate dead-letter queue,
which AWS applies server-side.

### Redis

The Redis adapter requires Redis to be correctly configured and running on the server, as well as
the `redis` extension installed with PHP:

```php
use Pop\Queue\Adapter\Redis;

$adapter = new Redis();
```

The Redis adapter uses `localhost` and port `6379` as defaults. It also manages the jobs with the
Redis server by means of a key prefix. By default, that prefix is set to `pop-queue`. If you would
like to use alternate values for any these, you can pass them into the constructor:

```php
$adapter = new Redis('my.redis.server.com', 6380, 'my-queue');
```

The remaining constructor parameters are `$priority`, `$leaseSeconds`, `$password` and `$context`:

```php
$adapter = new Redis('my.redis.server.com', 6380, 'my-queue', 'FILO', 30, 'my-password');
```

A reserved job is leased for `$leaseSeconds` (60 by default). If the code that reserved it never
calls `delete()`, `release()` or `bury()` — for example, the worker process dies — the lease
expires and the job becomes reservable again by another worker instead of being stranded. Set it
comfortably longer than your longest expected job runtime — a job that routinely outlives its own
lease will be handed to a second worker while the first is still running it.

`$password` is sent to the server with `AUTH` after connecting, for a Redis instance that requires
authentication. `$context` is passed straight through to the `redis` extension's `connect()` call,
which is how a TLS connection is configured:

```php
$adapter = new Redis('my.redis.server.com', 6380, 'my-queue', null, 60, 'my-password', [
    'stream' => [
        'ssl' => [
            'verify_peer'      => true,
            'cafile'           => '/path/to/ca.pem',
            'verify_peer_name' => true
        ]
    ]
]);
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

The table utilized in the database to manage the jobs default to `pop_queue`. If you would like
to name it something else, you can pass that into the constructor:

```php
$adapter = new Database($db, 'my_queue_jobs'); 
```

The remaining constructor parameters are `$priority` and `$leaseSeconds`:

```php
$adapter = new Database($db, 'my_queue_jobs', 'FILO', 30); // 30-second lease, FILO priority
```

A reserved job is leased for `$leaseSeconds` (60 by default). If the code that reserved it never
calls `delete()`, `release()` or `bury()` — for example, the worker process dies — the lease
expires and the job becomes reservable again by another worker instead of being stranded. Set it
comfortably longer than your longest expected job runtime — a job that routinely outlives its own
lease will be handed to a second worker while the first is still running it.

[Top](#pop-queue)

### File

The file adapter only requires the location on disk where the queue data will be stored:

```php
use Pop\Queue\Adapter\File;

$adapter = new File(__DIR__ . '/queues'); 
```

The remaining constructor parameters are `$priority` and `$leaseSeconds`:

```php
$adapter = new File(__DIR__ . '/queues', 'FILO', 30); // 30-second lease, FILO priority
```

A reserved job is leased for `$leaseSeconds` (60 by default). If the code that reserved it never
calls `delete()`, `release()` or `bury()` — for example, the worker process dies — the lease
expires and the job becomes reservable again by another worker instead of being stranded. Set it
comfortably longer than your longest expected job runtime — a job that routinely outlives its own
lease will be handed to a second worker while the first is still running it.

[Top](#pop-queue)

### Memory

The memory adapter keeps everything in PHP arrays, for the lifetime of the current process only.
Nothing is persisted, so it requires no server, no extension and no disk access:

```php
use Pop\Queue\Adapter\Memory;

$adapter = new Memory();
```

It is the reference implementation of the adapter contract, implementing the full job lifecycle —
`delay()` eligibility, retry backoff applied by `release()`, and lease-based crash recovery — with
no server, extension or disk access required. `File`, `Database`, and `Redis` implement that same
lifecycle too (with real concurrency safety, since more than one process can reserve against them
at once); `Memory` is simply the simplest of the four to reason about and stand up in a test. A
reserved job is leased for 60 seconds by default; if the code that reserved it never calls
`delete()`, `release()` or `bury()` (for example, the worker process dies), the lease expires and
the job becomes reservable again instead of being stranded. The lease length, and the queue
priority, can be passed into the constructor:

```php
$adapter = new Memory(30, 'FILO'); // 30-second lease, FILO priority
```

Note the argument order: `Memory` takes `$leaseSeconds` **first**, ahead of `$priority`, while
`File`, `Database` and `Redis` all take `$leaseSeconds` **after** `$priority` (their `$priority`
parameter predates leasing, and new parameters could only be appended). The two orders are not
interchangeable — check the constructor signature when switching an application between adapters.

Because of all of that, it's the recommended adapter for testing — both for this component's own
test suite and as a drop-in test double in an application that consumes it, where it lets you
exercise queue behavior (including delay, backoff and lease expiry) without standing up Redis, a
database or SQS.

[Top](#pop-queue)

### AWS SQS

The Amazon AWS SQS adapter interfaces with the AWS SQS service and requires the following credentials
and access information to be obtained from the AWS administration console:

- AWS Key
- AWS Secret
- AWS Region
- AWS Version (usually `latest`)
- The AWS Queue URL

*Make sure the correct permissions are granted to the user role attempting to access the SQS service.*

```php
use Pop\Queue\Adapter\Sqs;
use Aws\Sqs\SqsClient;

$client = new SqsClient([
        'key'    => 'AWS_KEY',
        'secret' => 'AWS_SECRET',
    ],
    'region'  => 'AWS_REGION',
    'version' => 'AWS_VERSION'
]);

$adapter = new Sqs($client, 'YOUR_AWS_QUEUE_URL');
```

The SQS adapter has some limitations in its behavior. It does not support scheduled tasks and can only
be used for jobs. Furthermore, the AWS SQS service offers two queue types - standard and FIFO. The FIFO
queue enforces a strict FIFO order and delivers a consistent behavior when pushing and popping jobs to
and from the queue. The standard queue is not as strict and there may be unexpected behavior regarding
the order and availability of the jobs stacked in the queue, depending on the frequency of requests.

#### Injecting the adapter into the queue

Once any adapter object is created, it can be passed into the queue object:

```php
use Pop\Queue\Queue;

$queue = Queue::create('pop-queue', $adapter); 
```

[Top](#pop-queue)

Queues
------

As shown in the [quickstart](#quickstart) example above, the queue object acts as the 
go-between for jobs and the queue storage adapter. Simply adding jobs or tasks to a queue
object will push them to the storage object, where they will wait until their turn is
called.

As shown in the example below, multiple jobs and multiple tasks can be added to the same queue:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Process\Job;
use Pop\Queue\Process\Task;

$job1 = Job::create(function() {
    echo 'This is job #1' . PHP_EOL;
});

$job2 = Job::create(function() {
    echo 'This is job #2' . PHP_EOL;
});

$task1 = Task::create(function() {
    echo 'This is scheduled task #1' . PHP_EOL;
})->every30Minutes();

$task2 = Task::create(function() {
    echo 'This is scheduled task #2' . PHP_EOL;
})->sundays();

$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'), Queue::FILO);
$queue->addJobs([$job1, $job2])
    ->addTasks([$task1, $task2]);
```

### Priority

A queue can have one of two priorities:
 
- **FIFO:** First In, First Out (default)
- **FILO:** First In, Last Out

This simply means that with FIFO, the first job pushed in will be the **first** job popped off.
And with FILO, the first job pushed in will be the **last** job popped off, as the most recently
pushed job will be popped off instead.

*(When you use a SQS FIFO queue, the queue priority is automatically set to FIFO)*

### Events

A queue can fire lifecycle events around job and task execution, for observability - logging, metrics,
tracing, whatever a listener wants to do. This reuses `Pop\Event\Manager`, the same event system
`Pop\Application` uses for its own lifecycle (`app.init`, `app.route.pre`, etc.), rather than a separate
mechanism:

```php
use Pop\Event\Manager;

$events = new Manager();
$events->on('queue.job.post', function($job, $queue) {
    echo 'Job ' . $job->getJobId() . ' completed on queue ' . $queue->getName() . PHP_EOL;
});

$queue->setEvents($events);
```

**Where the event manager comes from:** if you call `$queue->setEvents()`, that manager is used - full stop.
If you don't, and you pass a `Pop\Application` into `work()`/`run()` that has its own event manager
registered, that application's manager is used instead. If neither is set, event firing is a silent no-op.
Setting a queue-level manager completely suppresses the application fallback for that queue - it's one or
the other, never both.

**Listener signatures are positional, not a single params array.** `Manager::trigger()` passes its params
array to each listener with the keys stripped - so a listener for an event fired with
`['job' => $job, 'queue' => $this]` must be written `function($job, $queue) { ... }`, **not**
`function($params) { ... }` expecting one array argument. (`Manager::trigger()` also always appends a
trailing `result` value itself, so an extra trailing positional argument is always present too - a listener
that declares fewer parameters simply ignores it.)

The events fired:

| Event | When | Params |
|---|---|---|
| `queue.job.pre` | About to run a valid, reserved job | `job`, `queue` |
| `queue.job.post` | Job ran and completed successfully | `job`, `queue` |
| `queue.job.failed` | Job threw | `job`, `queue`, `exception` |
| `queue.job.buried` | Job was permanently buried (already invalid before it could run, or failed and no longer valid for retry) | `job`, `queue`, `reason` |
| `queue.task.pre` | About to run a due, claimed task | `task`, `queue` |
| `queue.task.post` | Task ran and completed successfully | `task`, `queue` |
| `queue.task.failed` | Task threw | `task`, `queue`, `exception` |

A listener that itself throws propagates straight out of `work()`/`run()` - it is never caught or
misattributed as the job/task having failed. One consequence worth knowing: if a `queue.job.pre` listener
throws, the job stays reserved but never runs (it self-heals once its lease expires and gets reclaimed); if
a `queue.task.pre` listener throws, that task's current due-window is already claimed and is simply skipped
- the task runs again on its next due-window as normal.

Workers
-------

The worker object allows you to configure and manage multiple queues from one worker object.
Once you've added jobs or tasks to a queue, or queues, you can add those queue objects to 
the worker object to manage from there. Queues are given names to assist with managing and
calling them within the worker object:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

// Call up the queue and pass it to a worker object
$queue1  = new Queue('pop-queue1', new File(__DIR__ . '/queue1'));
$queue2  = new Queue('pop-queue2', new File(__DIR__ . '/queue2'));
$worker = Worker::create([$queue1, $queue2]);
```

From there, you can trigger the next job of a particular queue with the `work()` method:

```php
$worker->work('pop-queue1');
```

Or, you can trigger the next jobs of all the registered queues:

```php
$worker->workAll();
```

#### Queue weights

Queues can be registered with a weight, so a worker servicing several queues can express that
some matter more than others:

```php
$worker = Worker::create();
$worker->addQueue($queue1, 10); // high weight
$worker->addQueue($queue2, 1);  // low weight
```

Weight defaults to `0` if never set, so registering a queue without a weight behaves exactly as
before — queues are serviced in the order they were added. `getQueues()`, `workAll()`, and
`runAll()` all iterate queues in weight order (highest first). This is unrelated to the FIFO/FILO
`Priority` setting above, which controls the order jobs are popped off *within* a single queue -
weight controls which *queue* a worker considers first, not which job.

Calling `work()` with no queue name tries every registered queue in weight order and returns the
first job successfully claimed - the highest-weight queue is always preferred, falling through to
lower-weight queues only when nothing is available higher up:

```php
$job = $worker->work(); // tries $queue1 first, then $queue2
```

Managing the scheduled tasks is similar with the `run()` method:

```php
$worker->run('pop-queue1');
```

Or, trigger all the next scheduled tasks of all the registered queues:

```php
$worker->runAll();
```

#### Clearing the queues

You can clear the queues in a few different ways:

- `$worker->clear(string $queueName)`       // Clear completed jobs from queue
- `$worker->clearFailed(string $queueName)` // Clear failed jobs from queue
- `$worker->clearTasks(string $queueName)`  // Clear tasks from queue
- `$worker->clearAll()`                     // Clear completed jobs from all queues
- `$worker->clearAllFailed()`               // Clear failed jobs from all queues
- `$worker->clearAllTasks()`                // Clear tasks from all queues

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

