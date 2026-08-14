<?php

namespace Pop\Queue\Test\TestAsset;

use Pop\Console\Command\AbstractCommand;

/**
 * Reports whether the dispatch layer handed it an Application and a Console.
 */
class ContextCommand extends AbstractCommand
{

    public function handle(): void
    {
        echo 'application:' . var_export($this->hasApplication(), true) . PHP_EOL;
        echo 'console:' . var_export($this->hasConsole(), true);
    }

}
