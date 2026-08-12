<?php
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Registry;

/**
 * Registry abstract class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
abstract class AbstractRegistry implements RegistryInterface
{

    /**
     * Whether a record's heartbeat is older than the given number of
     * seconds. Shared by every backend's prune() so the cutoff rule can't
     * drift between them.
     *
     * @param  WorkerRecord $record
     * @param  int          $olderThanSeconds
     * @return bool
     */
    protected function isExpired(WorkerRecord $record, int $olderThanSeconds): bool
    {
        return ((time() - $record->getLastSeenAt()) > $olderThanSeconds);
    }

    /**
     * Encode a record for storage
     *
     * JSON, deliberately - registry records are plain scalars and arrays,
     * so this carries none of the unserialize() object-injection surface
     * that job payloads do.
     *
     * @param  WorkerRecord $record
     * @return string
     */
    protected function encode(WorkerRecord $record): string
    {
        return json_encode($record->toArray());
    }

    /**
     * Decode a stored record, or null if the payload is unusable
     *
     * @param  ?string $payload
     * @return ?WorkerRecord
     */
    protected function decode(?string $payload): ?WorkerRecord
    {
        if (empty($payload)) {
            return null;
        }

        $data = json_decode($payload, true);

        return (is_array($data) && !empty($data['id'])) ? WorkerRecord::fromArray($data) : null;
    }

}
