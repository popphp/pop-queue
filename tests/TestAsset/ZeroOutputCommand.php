<?php

namespace Pop\Queue\Test\TestAsset;

use Pop\Console\Command\AbstractCommand;

/**
 * Emits a literal "0" line between two ordinary lines, plus a blank line -
 * the case that separates a truthiness filter from an empty-string filter.
 */
class ZeroOutputCommand extends AbstractCommand
{

    public function handle(): void
    {
        echo 'alpha' . PHP_EOL;
        echo '0' . PHP_EOL;
        echo PHP_EOL;
        echo 'bravo';
    }

}
