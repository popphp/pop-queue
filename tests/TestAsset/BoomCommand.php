<?php

namespace Pop\Queue\Test\TestAsset;

use Pop\Console\Command\AbstractCommand;

/**
 * Throws part-way through, after having already written output - used to
 * prove runCommand() does not leak the output buffer it opened.
 */
class BoomCommand extends AbstractCommand
{

    public function handle(): void
    {
        echo 'before boom' . PHP_EOL;
        throw new \RuntimeException('kaboom');
    }

}
