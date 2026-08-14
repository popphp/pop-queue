<?php

namespace Pop\Queue\Test\TestAsset;

use Pop\Application;
use Pop\Console\Console;
use Pop\Console\Command\AbstractCommand;

/**
 * Echoes back a single argument verbatim, so a test can prove a value
 * containing spaces survived the trip through the router.
 */
class NotifyCommand extends AbstractCommand
{

    public function __construct(?Application $application = null, Console $console = new Console(120))
    {
        parent::__construct($application, $console, 'notify', '<message>', 'Sends a notification');
    }

    public function handle(string $message): void
    {
        echo 'MSG=[' . $message . ']';
    }

}
