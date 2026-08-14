<?php

namespace Pop\Queue\Test\TestAsset;

use Pop\Application;
use Pop\Console\Console;
use Pop\Console\Command\AbstractCommand;

/**
 * A command that names and documents itself in a no-arg constructor - the
 * shape CommandRegistry::loadRoutes() produces.
 */
class GreetCommand extends AbstractCommand
{

    public function __construct(?Application $application = null, Console $console = new Console(120))
    {
        parent::__construct($application, $console, 'greet', '<name>', 'Greets somebody by name');
    }

    public function handle(string $name): void
    {
        echo 'Hello ' . $name;
    }

}
