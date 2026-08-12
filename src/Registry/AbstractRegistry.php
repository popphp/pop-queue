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
     * @throws Exception
     */
    protected function encode(WorkerRecord $record): string
    {
        // JSON_INVALID_UTF8_SUBSTITUTE so a record with a stray invalid byte in
        // an operator-supplied name degrades to replacement characters rather
        // than vanishing: losing sight of a worker is worse than an ugly name.
        // The false-check is belt-and-braces - without declare(strict_types=1)
        // a false return would coerce to "" and silently destroy the record.
        $payload = json_encode($record->toArray(), JSON_INVALID_UTF8_SUBSTITUTE);

        if ($payload === false) {
            throw new Exception('Error: Unable to encode the worker record: ' . json_last_error_msg());
        }

        return $payload;
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

    /**
     * Remove every record whose heartbeat is older than the given number of
     * seconds, plus any stored entry that can no longer be decoded, returning
     * how many were removed in total.
     *
     * Concrete here rather than per-backend: the expiry half is expressible
     * purely in terms of all()/delete()/isExpired(), and four byte-identical
     * copies would be four places for the rule to drift.
     *
     * @param  int $olderThanSeconds
     * @return int
     */
    public function prune(int $olderThanSeconds): int
    {
        $removed = 0;

        foreach ($this->all() as $id => $record) {
            if ($this->isExpired($record, $olderThanSeconds)) {
                $this->delete($id);
                $removed++;
            }
        }

        return $removed + $this->purgeUndecodable();
    }

    /**
     * Remove stored entries that can no longer be decoded into a WorkerRecord,
     * returning how many were removed.
     *
     * These are invisible to all() by design (it skips them so one corrupt
     * entry cannot derail enumeration), which would otherwise make them
     * permanently unreapable - a truncated write or a bad payload would leak
     * for the lifetime of the store. Backends with real storage override this;
     * the default is a no-op for backends that cannot hold an undecodable
     * entry.
     *
     * @return int
     */
    protected function purgeUndecodable(): int
    {
        return 0;
    }

}
