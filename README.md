pop-queue
=========

[![Build Status](https://github.com/popphp/pop-queue/workflows/phpunit/badge.svg)](https://github.com/popphp/pop-queue/actions)
[![Coverage Status](http://cc.popphp.org/coverage.php?comp=pop-queue)](http://cc.popphp.org/pop-queue/)

[![Join the chat at https://popphp.slack.com](https://media.popphp.org/img/slack.svg)](https://popphp.slack.com)
[![Join the chat at https://discord.gg/D9JBxPa5](https://media.popphp.org/img/discord.svg)](https://discord.gg/D9JBxPa5)

* [Overview](#overview)
* [Install](#install)
* [Quickstart](#quickstart)
* [Workers](#workers)
* [Schedulers](#schedulers)
* [Adapters](#adapters)

Overview
--------
`pop-queue` is a job queue component that provides the ability to pass an executable job off to
a queue to be worked at a later date and time. Queues can either process jobs via sequential workers
or time-based schedulers. The available storage adapters for the queue component are:

- Database
- Redis
- File

And others can be written as needed, implementing the `AdapterInterface` and extending the `AbstractAdapter`.

`pop-queue` is a component of the [Pop PHP Framework](http://www.popphp.org/).

Install
-------

Install `pop-queue` using Composer.

    composer require popphp/pop-queue

Or, require it in your composer.json file

    "require": {
        "popphp/pop-queue" : "^2.0.0"
    }

Quickstart
----------

#### Pushing a Job onto a Queue:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;
use Pop\Queue\Processor\Worker;
use Pop\Queue\Processor\Jobs\Job;

$queue = new Queue('pop-queue', new File(__DIR__ . '/queue'));

$job1 = new Job(function() {
    echo 'This is job #1' . PHP_EOL;
});

$worker = new Worker();
$worker->addJob($job1);

$queue->addWorker($worker);

// Pushes the worker and its jobs onto the queue to be processed later
$queue->pushAll();
```

#### Processing the Job:

```php
use Pop\Queue\Queue;
use Pop\Queue\Adapter\File;

$queue = Queue::load('pop-queue', new File(__DIR__ . '/queue'));

$queue->processAll(); // Processes all the jobs on the queue stack
```

#### Scheduling a Job with a Queue:
