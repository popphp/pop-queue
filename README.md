pop-queue
=========

[![Build Status](https://github.com/popphp/pop-queue/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-queue/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-queue)](http://cc.popphp.org/pop-queue/)

[![Join the chat at https://discord.gg/TZjgT74U7E](https://media.popphp.org/img/discord.svg)](https://discord.gg/TZjgT74U7E)

* [Overview](#overview)
* [Install](#install)
    - [Requirements](#requirements)
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
    - [Signed payloads](#signed-payloads)
    - [Events](#events)
* [Workers](#workers)
    - [Queue weights](#queue-weights)
    - [Accessing the queues](#accessing-the-queues)
    - [Daemon mode](#daemon-mode)
    - [Clearing the queues](#clearing-the-queues)
* [Configuration](#configuration)
* [Upgrading to 3.0](#upgrading-to-30)

Overview
--------
`pop-queue` is a job queue component that provides the ability to pass executable jobs or tasks
off to a queue to be processed at a later date and time. Queues can either process jobs or scheduled
tasks. The jobs or tasks are stored with an available queue storage adapter until they are called to be
executed. The available storage adapters for the queue component are:

- Redis
- Database
- File
- Memory
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
        "popphp/pop-queue" : "^3.0.0"
    }

### Requirements

`pop-queue` requires **PHP 8.4.0 or greater**. Everything else depends on which features you use:

| Requirement | Needed for | Without it |
|---|---|---|
| `ext-redis` | The [Redis](#redis) adapter | The other adapters work normally |
| `aws/aws-sdk-php` | The [AWS SQS](#aws-sqs) adapter | The other adapters work normally |
| `proc_open()` | [CLI command](#cli-commands) jobs | `Symfony\Process` throws when the job runs |
| `ext-pcntl` | Job [timeouts](#attempts) on callable/command jobs, and signal-based [graceful shutdown](#daemon-mode) | Jobs run untimed; loops end only via `stop()` |

`aws/aws-sdk-php` is **not** installed automatically — install it separately if you use the SQS
adapter (see [AWS SQS](#aws-sqs)).

`proc_open()` is new in 3.0: earlier versions shelled out with `exec()`, which some hosts allow while
disabling `proc_open()`. If you're upgrading and use CLI command jobs, see
[Upgrading to 3.0](#upgrading-to-30).

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

If the callable needs access to the main application object, that object gets prepended to the
parameters of the callable. Write the job to accept it:

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

Then supply the application object at the point the job is actually worked. There are two ways to do
that. Pass it to the worker, which hands it down to every queue it services:

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

Or, if you're working a queue directly without a worker, pass it to `work()` (or `run()`) per call:

```php
$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));
$queue->work($application);
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

This runs via the shell (`Symfony\Process`'s `fromShellCommandline()`), so pipes, redirects, and
chaining work exactly as they would on a command line. Because it goes through a shell, avoid
building this string from anything that isn't a fully-trusted literal.

For a command built from any value you don't fully trust, pass an array instead:

```php
$job = Job::exec(['ls', '-la']);
```

This runs with no shell involved at all — each array element is passed directly to the process,
so shell metacharacters in any of them are inert. Prefer this form whenever part of the command
isn't a hardcoded literal.

A failing command (non-zero exit code) throws `Symfony\Component\Process\Exception\ProcessFailedException`,
which flows through `Queue::work()` like any other job failure — `failed()`, then `release()` or
`bury()` depending on remaining attempts. A job's `setTimeout()` is enforced by `Symfony\Process`
itself for exec jobs, which (unlike a plain `exec()` call) can actually terminate the underlying
child process on expiry, not just abandon it running in the background.

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

**NOTE:** `setBackoff()`'s delay is honored by `Memory`, `File`, `Database`, and `Redis` — a failed
job with a backoff set won't be retried until the delay elapses, on any of those four. `AWS SQS` is
the one exception: its `release()` deletes and re-sends the message without recomputing a delay, so a
backed-off job on that adapter retries immediately regardless of `setBackoff()`.

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

And a job can set an execution timeout:

```php
// Interrupt the job if it runs longer than 30 seconds
$job->setTimeout(30);
```

How that timeout is enforced depends on the job type. For [CLI command](#cli-commands) jobs it's
enforced by `Symfony\Process`, which needs no extension and can actually terminate the spawned child
process. For callable and application command jobs it's a *soft* timeout enforced with a `pcntl`
alarm — so it requires the `pcntl` extension, and without it those jobs run untimed.

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

Note: task claiming requires a `claimTaskRun()` method on the adapter, which is a breaking change for
third-party adapters written against 2.x — see [Upgrading to 3.0](#upgrading-to-30).

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
$task->every30Minutes()->runUntil('2027-11-30 23:59:59');
```

It can also accept a timestamp:

```php
// Using a valid UNIX timestamp
$task->every30Minutes()->runUntil(1827619199);
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

By default, there are five available adapters, but additional ones can be created. Which contract you
implement depends on whether your adapter needs to support scheduled tasks as well as jobs:

- **Jobs only** — implement `Pop\Queue\Adapter\AdapterInterface` and extend
  `Pop\Queue\Adapter\AbstractAdapter`. This covers `push()`/`reserve()`/`release()`/`delete()`/`bury()`,
  FIFO/FILO priority, and the dead-letter methods. (`AWS SQS` is the one bundled adapter at this level.)
- **Jobs and tasks** — implement `Pop\Queue\Adapter\TaskAdapterInterface` and extend
  `Pop\Queue\Adapter\AbstractTaskAdapter` (which itself extends `AbstractAdapter`). On top of the job
  contract, this adds `schedule()`, `getTask()`/`getTasks()`, `updateTask()`, `removeTask()`,
  `clearTasks()` — and `claimTaskRun()`, which is what makes task deduplication across multiple workers
  work (see [Tasks](#tasks) above).

`Queue::addTask()` type-checks the adapter against `TaskAdapterInterface` and throws if it doesn't
qualify, which is how the jobs-only restriction is enforced at runtime.

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

`Queue::fake()` is a shortcut for the common case — a `Memory`-backed queue with no further setup:

```php
use Pop\Queue\Queue;

$queue = Queue::fake(); // same as Queue::create('pop-queue', new Memory())
```

It accepts the same `$name`/`$priority` you'd pass to `Queue::create()`, plus an optional lease length:

```php
$queue = Queue::fake('test-queue', 'FILO', 30);
```

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

`aws/aws-sdk-php` is not installed automatically with this package — install it separately to use this
adapter:

```bash
composer require aws/aws-sdk-php
```

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

Priority can be set as the third constructor argument of the queue, or afterwards with
`setPriority()`:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'), Queue::FILO);

// Or set it after the fact
$queue->setPriority(Queue::FILO);
$queue->setPriority('FILO'); // the constants are just these two strings
```

The `Queue::FIFO` and `Queue::FILO` constants are available, and every adapter also accepts a
priority directly in its own constructor (see each adapter above for its exact argument position).
Setting it on the queue delegates to the adapter, so the two are equivalent.

To read it back:

```php
$queue->getPriority(); // 'FIFO' or 'FILO'
$queue->isFifo();      // bool
$queue->isFilo();      // bool
```

Because the same two orderings are commonly named LILO and LIFO, aliases are provided —
`isLilo()` is identical to `isFifo()`, and `isLifo()` is identical to `isFilo()`.

*(When you use a SQS FIFO queue, the queue priority is automatically set to FIFO)*

### Signed payloads

Every persistent storage adapter (`File`, `Database`, `Redis`, `Sqs`) serializes job and task objects
to persist them, and unserializes them back on read. By default, that's PHP's plain
`serialize()`/`unserialize()` — anyone who can write to the
underlying storage directly (a compromised Redis instance, SQL injection elsewhere in your app, a
writable queue directory) could otherwise plant a crafted payload and get it executed the moment a
worker unserializes it.

`Pop\Queue\Process\PayloadSigner` closes that gap. Call `setKey()` once, at application bootstrap,
before any queue or worker operation:

```php
use Pop\Queue\Process\PayloadSigner;

PayloadSigner::setKey($_ENV['QUEUE_SIGNING_KEY']);
```

Once a key is configured, every adapter HMAC-signs a payload before writing it and verifies that
signature before ever unserializing it back — a payload that doesn't verify (tampered, or written by
anything other than your own application) is treated exactly like a corrupt payload today: skipped,
never unserialized, never executed.

This is opt-in - with no key configured (the default), behavior is completely unchanged from
before. There's no fallback-to-unsigned read path once a key is set, by design: turning signing on
mid-lifecycle means anything already queued before that point will fail verification and be
skipped, so either drain your queues first or accept that any in-flight jobs from before the
rollout are dropped.

[Top](#pop-queue)

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

`getEvents()` returns the currently-set manager (or `null`), `events()` is a bare alias for it, and
`hasEvents()` returns whether one has been set at all.

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

#### Accessing the queues

Queues can be added one at a time with `addQueue()` (optionally with a [weight](#queue-weights)), or
several at once with `addQueues()`:

```php
$worker->addQueue($queue1);
$worker->addQueue($queue2, 10);   // with a weight
$worker->addQueues([$queue3, $queue4]);
```

And read back by name:

```php
$worker->getQueue('pop-queue1');   // ?Queue - null if not registered
$worker->hasQueue('pop-queue1');   // bool
$worker->getWeight('pop-queue1');  // int - 0 if never weighted
$worker->getQueues();              // array of all queues, in weight order
```

The worker also implements `ArrayAccess`, `Countable` and `IteratorAggregate`, and exposes queues as
magic properties — so the same collection can be reached in whichever style reads best:

```php
// Array access, keyed by queue name
$worker['pop-queue1'] = $queue1;
$queue = $worker['pop-queue1'];
isset($worker['pop-queue1']);
unset($worker['pop-queue1']);

// Property access, same thing
$worker->{'pop-queue1'} = $queue1;
$queue = $worker->{'pop-queue1'};

// Countable and iterable
count($worker);
foreach ($worker as $name => $queue) {
    // ...
}
```

Iteration and `getQueues()` both return queues in weight order (highest first), not insertion order.

**One gotcha when *setting* via array or property access:** the offset you write to is ignored — the
queue registers under its own `getName()`. So this is not a rename:

```php
$queue = new Queue('pop-queue1', $adapter);

$worker['some-other-name'] = $queue;

isset($worker['some-other-name']); // false
isset($worker['pop-queue1']);      // true - keyed by the queue's own name
```

If the worker was given an application object, it's available too:

```php
$worker->getApplication(); // ?Application ($worker->application() is an alias)
$worker->hasApplication(); // bool
```

#### Daemon mode

Instead of being triggered externally (e.g. from a cron job), a worker can service its queues
continuously as a long-running loop with `workLoop()` and `runLoop()`. Each calls its non-looping
counterpart - `workAll()` and `runAll()` respectively - every iteration, forever, until stopped:

```php
$worker->workLoop(); // runs forever, servicing jobs as they arrive
```

Both accept an `int $sleepSeconds = 1` parameter: the loop only sleeps that many seconds between
passes when a full pass found nothing to do anywhere (every queue empty for `workLoop()`, nothing due
for `runLoop()`) - if work was found, it loops again immediately, with no sleep. A `$sleepSeconds`
below `0` is silently clamped to `0`.

**These are two separate loops - run both, in two separate OS processes, to get full daemon
behavior.** `workLoop()` alone never runs scheduled tasks, and `runLoop()` alone never works jobs. To
get both, run two independent processes - two systemd units, two supervisor programs, or two
backgrounded shell invocations:

```bash
$ php worker.php workLoop &
$ php worker.php runLoop &
```

This is a deliberate design choice, not a missing feature: `runAll()` can itself block for up to ~59
seconds when sub-minute tasks exist, and a single synchronous PHP process can't service jobs during
that window.

**Graceful shutdown.** `stop()` sets an internal flag that both loops check; `isStopped()` reads it.
When `ext-pcntl` is loaded, `workLoop()`/`runLoop()` also install SIGTERM/SIGINT handlers
automatically that call `stop()` - so `kill` or Ctrl-C trigger the same graceful path. Without
`ext-pcntl`, only calling `stop()` programmatically can end a loop.

State the shutdown guarantee precisely: the loop never tears down or aborts a job or task
mid-execution - the current iteration's job/task always finishes running before the loop exits.
However, a *blocking call inside that job's own code* (e.g. `sleep()`) can be interrupted early by the
signal itself - PHP's `sleep()` returns early, with the remaining seconds, when a signal is delivered
during it. The job's remaining PHP statements still run afterward, but code relying on a `sleep()`
call completing its full duration for pacing or rate-limiting should be aware shutdown can cut it
short. This applies to any blocking I/O in job code generally (sockets, `stream_select()`, etc.), not
just `sleep()`.

There's also an accepted shutdown-latency limitation specific to `runLoop()`: a stop signal arriving
while `runAll()`'s own internal sub-minute-task tick loop is mid-flight (up to ~59 seconds) isn't
noticed until that call returns, since the stop flag is only checked between `runLoop()` iterations,
not injected into `Queue::run()`'s own loop.

**Daemon mode makes retry configuration effectively mandatory.** Under the old cron-every-minute
model, a job that always fails with default settings (`maxAttempts` unset = unlimited, no backoff
configured = 0-second delay) retried once per minute - self-limiting and harmless. Under `workLoop()`,
that same job is retried immediately, in a tight loop, at full CPU, because a reserved-then-failed job
still counts as "work was found" for the idle-backoff check (it doesn't sleep between retries).
**`setMaxAttempts()` and/or `setBackoff()` should be treated as required when jobs run under
`workLoop()`** - see [Attempts](#attempts) above for how those are configured.

**Run under a process supervisor.** An uncaught exception from an adapter (e.g. a dropped Redis
connection, a database timeout, a full disk) or from a worker-level event listener propagates out of
`workLoop()`/`runLoop()` and ends the process - there is no internal retry or restart. A daemon
deployment should run under a process supervisor (systemd, supervisor, pm2, or equivalent) configured
to restart the process on exit.

**Worker-level events.** Like queues (see [Events](#events) above), a worker can fire lifecycle events
around its loops, using the same `Pop\Event\Manager`:

```php
use Pop\Event\Manager;

$events = new Manager();
$events->on('worker.work_loop.idle', function($worker) {
    echo 'No work found this pass' . PHP_EOL;
});

$worker->setEvents($events);
```

`getEvents()` returns the currently-set manager (or `null`), `events()` is a bare alias for it, and
`hasEvents()` returns whether one has been set at all - all three behave the same way on `Worker` as
they do on `Queue`.

The events fired:

| Event | When | Params |
|---|---|---|
| `worker.work_loop.tick` | After every `workAll()` pass inside `workLoop()` | `jobs`, `worker` |
| `worker.work_loop.idle` | A `workLoop()` pass found no work anywhere, right before the backoff sleep | `worker` |
| `worker.work_loop.shutdown` | `workLoop()` is about to return after being stopped | `worker` |
| `worker.run_loop.tick` | After every `runAll()` pass inside `runLoop()` | `tasks`, `worker` |
| `worker.run_loop.idle` | A `runLoop()` pass found nothing due anywhere, right before the backoff sleep | `worker` |
| `worker.run_loop.shutdown` | `runLoop()` is about to return after being stopped | `worker` |

**One API asymmetry to be aware of:** `Queue`'s event resolution takes a per-call `?Application
$application` argument on `work()`/`run()`, so which application's event manager is used (when no
queue-level manager is set) can vary call to call. `Worker`'s event resolution has no such per-call
override - it resolves only against whatever `Application` was passed into `Worker`'s own
constructor. A user expecting `Worker` to behave exactly like `Queue` here will be surprised.

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
as needed. There are two supported ways to run that manager: triggering it periodically via
cron, or running it continuously as a daemon process (see [Daemon mode](#daemon-mode) above).

**Cron.** Assuming you have a CLI application that processes the queue via a command like:

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

**Daemon mode.** Instead of a periodic cron trigger, `workLoop()`/`runLoop()` let that same manager
run as a long-lived process that services its queues continuously - see [Daemon mode](#daemon-mode) above
for the full picture, including why it takes two separate processes and what to configure before
relying on it.

[Top](#pop-queue)

Upgrading to 3.0
----------------

**If your application uses `Queue`, `Worker`, `Job` and `Task` in the documented way, 3.0 should be a
drop-in upgrade.** Those public APIs only gained methods in 3.0 — nothing was removed or renamed. The
breaking changes below affect custom adapters, custom `JobInterface` implementations, and CLI command
jobs.

**1. The adapter contract was rewritten.** This is the largest change. Jobs are no longer popped in a
single step; they're reserved, then explicitly resolved. `AdapterInterface` changed as follows:

| 2.x | 3.0 |
|---|---|
| `pop()` | `reserve()`, then one of `delete()` (success), `release()` (retry) or `bury()` (give up) |
| `hasFailedJob()`, `getFailedJob()`, `hasFailedJobs()`, `getFailedJobs()`, `clearFailed()` | `hasDeadJobs()`, `countDead()`, `getDeadJob()`, `getDeadJobs()`, `retryDeadJob()`, `deleteDeadJob()`, `clearDead()` |
| `getStart()`, `getEnd()`, `getStatus()` | removed — replaced by `count()` |

This only affects adapters you wrote yourself. Note that `Queue::clearFailed()` and
`Worker::clearFailed()` still exist and still work — they now delegate to the adapter's `clearDead()` —
so application code calling those needs no change.

The rewrite also added a reservation *lease* to `Memory`, `File`, `Database` and `Redis`, so a job
whose worker dies is reclaimed rather than stranded. (SQS needs none — AWS's own visibility timeout
already does this server-side.) A custom adapter is responsible for its own lease handling.

**2. `TaskAdapterInterface` gained `claimTaskRun()`.** Any adapter implementing that interface directly
must now implement this method too. It's what lets multiple workers share one storage backend without
double-running a scheduled task — see [Tasks](#tasks).

**3. `JobInterface::setExec()`/`getExec()` were widened** to accept and return `string|array` rather
than `string`/`?string`, to support the shell-free array form of [CLI commands](#cli-commands). A class
implementing `JobInterface` directly with the old narrower signatures will fail to load until widened
to match.

**4. CLI command jobs now run through `Symfony\Process` instead of `exec()`.** Three consequences:

- They require `proc_open()` (see [Requirements](#requirements)). Some shared hosts disable it while
  leaving `exec()` enabled.
- **A command that exits non-zero now fails the job.** In 2.x the exit code was never checked, so a
  failing command silently completed successfully. If you have CLI jobs that routinely exit non-zero
  without that being an error, they will now be retried and eventually buried.
- `setTimeout()` now actually terminates the child process on expiry rather than abandoning it.

**5. `aws/aws-sdk-php` is no longer installed automatically.** If you use the [AWS SQS](#aws-sqs)
adapter, add it explicitly:

```bash
composer require aws/aws-sdk-php
```

Everyone else gets a substantially smaller install.

[Top](#pop-queue)

