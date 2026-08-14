<?php

namespace Pop\Queue\Test\TestAsset;

/**
 * A plain class used as queued work - no route, no framework base class.
 * Pinned by JobTest so the class-string job forms documented in the README
 * can't silently regress.
 */
class DigestService
{

    public mixed $constructedWith = null;

    public function __construct($application = null, ?string $to = null)
    {
        $this->constructedWith = $to;
    }

    public function handle($application, string $to = 'nobody'): string
    {
        return 'sent to ' . $to . ' (app: ' . (($application !== null) ? 'yes' : 'no') . ')';
    }

    public static function handleStatic($application, string $to = 'nobody'): string
    {
        return 'static sent to ' . $to;
    }

}
