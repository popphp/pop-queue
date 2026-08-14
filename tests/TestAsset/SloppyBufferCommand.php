<?php

namespace Pop\Queue\Test\TestAsset;

use Pop\Console\Command\AbstractCommand;

/**
 * Opens an output buffer and never closes it - a bug in the command, but one
 * a worker must survive rather than carry for the rest of its life.
 */
class SloppyBufferCommand extends AbstractCommand
{

    public function handle(): void
    {
        echo 'captured' . PHP_EOL;
        ob_start();
        echo 'stranded';
    }

}
