pop-queue
=========

[![Build Status](https://travis-ci.org/popphp/pop-queue.svg?branch=master)](https://travis-ci.org/popphp/pop-queue)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-queue)](http://cc.popphp.org/pop-queue/)

OVERVIEW
--------
`pop-queue` is a job queue component that provides the ability to pass an executable job off to
a queue to be worked at a later date. Queues can either process jobs via sequential workers or
schedulers. The available storage adapters for the queue component are:

- Database
- Redis
- File

And others can be written as needed, implementing the `AdapterInterface` and extending the `AbstractAdapter`.

`pop-queue` is a component of the [Pop PHP Framework](http://www.popphp.org/).

INSTALL
-------

Install `pop-queue` using Composer.

    composer require popphp/pop-queue

BASIC USAGE
-----------

### Pushing a Job onto a Queue:

```php
use Pop\Db\Db;
use Pop\Queue;
use Pop\Queue\Processor;
use Pop\Queue\Processor\Jobs\Job;

$db = Db::mysqlConnect([
    'database' => 'popdb',
    'username' => 'popuser',
    'password' => '12pop34'
]);

$queue = new Queue\Queue('pop-queue', new Queue\Adapter\Db($db));

$job1 = new Job(function() {
    echo 'This is job #1' . PHP_EOL;
});

$worker = new Processor\Worker();
$worker->addJob($job1);

$queue->addWorker($worker);

$queue->pushAll(); // Pushes the worker and its jobs onto the queue to be processed later

```

### Loading a Queue and Processing the Jobs:

```php
use Pop\Db\Db;
use Pop\Queue;
use Pop\Queue\Processor;
use Pop\Queue\Processor\Jobs\Job;

$db = Db::mysqlConnect([
    'database' => 'popdb',
    'username' => 'popuser',
    'password' => '12pop34'
]);

$queue = Queue\Queue::load('pop-queue', new Queue\Adapter\Db($db));

$queue->processAll(); // Processes the jobs on the queue stack

```

### Scheduling a Job

```php
use Pop\Db\Db;
use Pop\Queue;
use Pop\Queue\Processor;
use Pop\Queue\Processor\Jobs\Job;

$db = Db::mysqlConnect([
    'database' => 'popdb',
    'username' => 'popuser',
    'password' => '12pop34'
]);

$queue = new Queue\Queue('pop-queue', new Queue\Adapter\Db($db));

$job1 = new Job(function() {
    echo 'This is job #1' . PHP_EOL;
});

$scheduler = new Processor\Scheduler();
$scheduler->addJob($job1)
    ->every5Minute(); // This job will run every 5 minutes

$queue->addScheduler($scheduler);
$queue->pushAll();

```

### Using an Application Command

Presuming you have an application that has a route define like below:

```php
$app = new Pop\Application([
     'routes' => [
         'foo bar' => [
              'controller' => 'ConsoleController',
              'action'     => 'foo'
         ]
     ]
]);
```

Where the CLI application command to run that would be:

```bash
$ ./app foo bar
```

You can pass an application command to a queue to run a later or scheduled time:

```php
use Pop\Queue\Processor;
use Pop\Queue\Processor\Jobs\Job;

$appJob = Job::command('foo bar');

$scheduler = new Processor\Scheduler();
$scheduler->addJob($appJob)
    ->every10Minutes(); // This will trigger the application command every 10 minutes

```

When pulling up the queue to run the application command, the application object then
needs to be registered with the queue object in order for it to find the command to execute:

```php
use Pop\Queue;

$adapter = new Queue\Adapter\File('queues');
$queue   = Queue\Queue::load('pop-queue', $adapter, $application);

```

### Executing a System Command

##### WARNING: Take caution in running and executing system commands from a PHP application

You can pass a system command to be executed later, assuming the system is
***safely*** configured to allow that to happen:

```php
use Pop\Queue\Processor;
use Pop\Queue\Processor\Jobs\Job;

$sysJob = Job::exec('ls -la');

$worker = new Processor\Worker();
$worker->addJob($sysJob);

```

### Managing Queue

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
