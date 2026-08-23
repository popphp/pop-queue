<?php
declare(strict_types=1);
/**
 * Pop PHP Framework (https://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Registry\Adapter;

use Pop\Queue\Registry\AbstractRegistry;
use Pop\Queue\Registry\Exception;
use Pop\Queue\Registry\WorkerRecord;

/**
 * File registry class
 *
 * One JSON file per worker, in a single flat folder.
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <nick@popphp.org>
 * @copyright  Copyright (c) 2009-2026 Nick Sagona, III
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class File extends AbstractRegistry
{

    /**
     * Folder holding the record files
     * @var string
     */
    protected string $folder;

    /**
     * Constructor
     *
     * @param  string $folder
     * @throws Exception
     */
    public function __construct(string $folder)
    {
        if (!file_exists($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' does not exist.");
        }
        if (!is_writable($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' is not writable.");
        }

        $this->folder = $folder;
    }

    /**
     * Get the folder
     *
     * @return string
     */
    public function getFolder(): string
    {
        return $this->folder;
    }

    /**
     * Path of the file backing a given worker ID
     *
     * IDs contain ':' and hostnames may contain '.', so the filename is a
     * sanitized form. The sanitized form alone is not injective ('a.b' and
     * 'a_b' both map to 'a_b'), so a short hash of the original ID is
     * appended to keep distinct IDs in distinct files while the readable
     * part stays useful for anyone browsing the folder.
     *
     * @param  string $id
     * @return string
     */
    protected function recordPath(string $id): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'worker-' .
            preg_replace('/[^A-Za-z0-9_\-]/', '_', $id) . '-' . substr(sha1($id), 0, 8) . '.json';
    }

    public function write(WorkerRecord $record): void
    {
        file_put_contents($this->recordPath($record->getId()), $this->encode($record));
    }

    public function read(string $id): ?WorkerRecord
    {
        return $this->readRecord($this->recordPath($id));
    }

    public function all(): array
    {
        $records = [];

        foreach ($this->recordFiles() as $file) {
            // readRecord() returns null for a corrupt/truncated/unreadable file,
            // which is skipped rather than allowed to derail enumeration.
            $record = $this->readRecord($this->folder . DIRECTORY_SEPARATOR . $file);
            if ($record !== null) {
                $records[$record->getId()] = $record;
            }
        }

        return $records;
    }

    /**
     * Read and decode a record file, or null if it is unreadable or unusable
     *
     * The false-check is what keeps an unreadable file on the same graceful
     * path as an undecodable one. file_get_contents() returns false, not '',
     * when the read itself fails - and a worker registry is read while other
     * workers are pruning it, so a file that passes file_exists() and is then
     * unlinked before the read lands is an ordinary race, not corruption.
     * Under declare(strict_types=1) handing that false to decode(?string)
     * would be a TypeError, turning a routine race into a crash that takes
     * out enumeration for every other worker in the registry.
     *
     * @param  string $path
     * @return ?WorkerRecord
     */
    protected function readRecord(string $path): ?WorkerRecord
    {
        if (!file_exists($path)) {
            return null;
        }

        // Suppressed deliberately: a registry read losing a race to a concurrent
        // prune is expected operation, not a fault worth emitting a warning for.
        // The false return is what this method acts on.
        $payload = @file_get_contents($path);

        return ($payload !== false) ? $this->decode($payload) : null;
    }

    public function delete(string $id): void
    {
        $path = $this->recordPath($id);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    protected function purgeUndecodable(): int
    {
        $removed = 0;

        foreach ($this->recordFiles() as $file) {
            $path = $this->folder . DIRECTORY_SEPARATOR . $file;

            // Counting the unlink rather than the decode keeps the tally honest
            // when another worker prunes the same registry concurrently: a file
            // that vanished between the listing and here is already purged, and
            // claiming it twice would overstate what this call actually did.
            if (($this->readRecord($path) === null) && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * The record filenames in the folder
     *
     * @return array
     */
    protected function recordFiles(): array
    {
        if (!is_dir($this->folder)) {
            return [];
        }

        return array_values(array_filter(scandir($this->folder), function($value) {
            return (str_starts_with($value, 'worker-') && str_ends_with($value, '.json'));
        }));
    }

}
