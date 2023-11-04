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
* [Adapters](#adapters)
    - [File](#file)
    - [Database](#database)
    - [Redis](#redis)
* [Workers](#workers)
* [Jobs](#jobs)
    - [Callables](#callables)
    - [Application Commands](*application-commands)
    - [CLI Commands](*cli-commands)
* [Tasks](#tasks)
    - [Scheduling](#scheduling)
* [Tips](#tips)

Overview
--------
`pop-queue` is a job queue component that provides the ability to pass an executable job off to a
queue to be processed at a later date and time. Queues can either process jobs or scheduled tasks
via workers. The available storage adapters for the queue component are:

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
$worker = new Worker();
$worker->addJob($job1);

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

The queue object utilizes worker objects as managers of the jobs and tasks assigned to them.
The jobs are stored with the selected storage adapter. You can assign multiple jobs or tasks
to a worker. And you can assign multiple works to a queue. For reference, queues have a name, which 

### Completed Jobs

### Failed Jobs

[Top](#pop-queue)

Adapters
--------

### File

### Database

### Redis

[Top](#pop-queue)

Workers
-------

[Top](#pop-queue)

Jobs
----

### Callables

### Application Commands

### CLI Commands

[Top](#pop-queue)

Tasks
-----

### Scheduling

Tips
----

[Top](#pop-queue)
