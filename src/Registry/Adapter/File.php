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
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
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
     * sanitized form. Every ID carries a random suffix, so distinct IDs do
     * not collide once sanitized.
     *
     * @param  string $id
     * @return string
     */
    protected function recordPath(string $id): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'worker-' .
            preg_replace('/[^A-Za-z0-9_\-]/', '_', $id) . '.json';
    }

    public function write(WorkerRecord $record): void
    {
        file_put_contents($this->recordPath($record->getId()), $this->encode($record));
    }

    public function read(string $id): ?WorkerRecord
    {
        $path = $this->recordPath($id);

        return file_exists($path) ? $this->decode(file_get_contents($path)) : null;
    }

    public function all(): array
    {
        $records = [];

        foreach ($this->recordFiles() as $file) {
            // decode() returns null for a corrupt/truncated file, which is
            // skipped rather than allowed to derail enumeration.
            $record = $this->decode(file_get_contents($this->folder . DIRECTORY_SEPARATOR . $file));
            if ($record !== null) {
                $records[$record->getId()] = $record;
            }
        }

        return $records;
    }

    public function delete(string $id): void
    {
        $path = $this->recordPath($id);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    public function prune(int $olderThanSeconds): int
    {
        $removed = 0;
        foreach ($this->all() as $id => $record) {
            if ($this->isExpired($record, $olderThanSeconds)) {
                $this->delete($id);
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
