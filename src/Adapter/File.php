<?php
declare(strict_types=1);
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
namespace Pop\Queue\Adapter;

use Pop\Queue\Process\AbstractJob;
use Pop\Queue\Process\PayloadSigner;
use Pop\Queue\Process\Task;

/**
 * File adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@noladev.com>
 * @copyright  Copyright (c) 2009-2027 NOLA Interactive, LLC.
 * @license    https://www.popphp.org/license     New BSD License
 * @version    3.0.0
 */
class File extends AbstractTaskAdapter
{

    /**
     * Folder
     * @var ?string
     */
    protected ?string $folder = null;

    /**
     * Reservation lease length, in seconds
     * @var int
     */
    protected int $leaseSeconds = 60;

    /**
     * Highest job index this adapter instance knows to be taken, or null when
     * it hasn't looked yet. Purely a starting hint for push() - never a source
     * of truth. See getEndIndex() for why being wrong in either direction is
     * safe.
     * @var ?int
     */
    protected ?int $endIndex = null;

    /**
     * Constructor
     *
     * Instantiate the file object
     *
     * @param  string  $folder
     * @param  ?string $priority
     * @param  int     $leaseSeconds
     * @throws Exception
     */
    public function __construct(string $folder, ?string $priority = null, int $leaseSeconds = 60)
    {
        if (!file_exists($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' does not exist.");
        }
        if (!is_writable($folder)) {
            throw new Exception("Error: The folder '" . $folder . "' is not writable.");
        }

        $this->folder       = $folder;
        $this->leaseSeconds = $leaseSeconds;

        if (!file_exists($this->pendingPath())) {
            mkdir($this->pendingPath());
        }
        if (!file_exists($this->reservedPath())) {
            mkdir($this->reservedPath());
        }

        parent::__construct($priority);
    }

    /**
     * Create file adapter
     *
     * @param  string  $folder
     * @param  ?string $priority
     * @param  int     $leaseSeconds
     * @throws Exception
     * @return File
     */
    public static function create(string $folder, ?string $priority = null, int $leaseSeconds = 60): File
    {
        return new self($folder, $priority, $leaseSeconds);
    }

    /**
     * Get folder
     *
     * @return ?string
     */
    public function getFolder(): ?string
    {
        return $this->folder;
    }

    /**
     * Get folder (alias)
     *
     * @return ?string
     */
    public function folder(): ?string
    {
        return $this->folder;
    }

    /**
     * Get the pending-jobs subdirectory path
     *
     * @return string
     */
    protected function pendingPath(): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'pending';
    }

    /**
     * Get the reserved-jobs subdirectory path
     *
     * @return string
     */
    protected function reservedPath(): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'reserved';
    }

    /**
     * Get queue end index across both pending and reserved jobs (indices must
     * stay unique across both so a reclaimed reserved job can never collide
     * with a newly-pushed one)
     *
     * Scans the directories once per adapter instance and then tracks the
     * index forward in memory, because the scan is what made push() quadratic:
     * it walked both directories on every single push, so the cost of pushing
     * the Nth job grew with the number of jobs already queued.
     *
     * Caching it is safe precisely because push() never trusted this value in
     * the first place - mkdir() is the real allocator, and push()'s retry loop
     * already handles the index being taken. A cached value that is too low
     * (another process pushed since the scan) costs one wasted mkdir() attempt
     * per collision and the loop walks up; a cached value that is too high
     * (jobs were cleared elsewhere) just leaves a gap in the numbering, which
     * nothing depends on - indices only ever need to be unique and ordered,
     * never contiguous.
     *
     * @return int
     */
    protected function getEndIndex(): int
    {
        if ($this->endIndex === null) {
            $indices = array_merge(
                array_map('intval', $this->getFolders($this->pendingPath())),
                array_map('intval', $this->getFolders($this->reservedPath()))
            );

            $this->endIndex = !empty($indices) ? max($indices) : 0;
        }

        return $this->endIndex;
    }

    /**
     * Read a payload file and verify its signature, or false if it can't be read
     *
     * Every caller already treats a false return as "unusable payload, skip it",
     * so an unreadable file joins the corrupt and tampered ones on that path.
     * The explicit check matters because file_get_contents() signals failure
     * with false rather than '', and under declare(strict_types=1) passing that
     * to PayloadSigner::verify(string) is a TypeError - which would turn an
     * everyday race (another worker claiming and unlinking the same payload
     * between the file_exists() check and the read) into a crashed worker.
     *
     * @param  string $path
     * @return string|false
     */
    protected function readVerifiedPayload(string $path): string|false
    {
        // Suppressed for the same reason as the @rename() in reclaimExpiredLeases():
        // losing this read to a concurrent worker is expected operation, not a
        // fault worth writing to the worker's stderr on every occurrence. The
        // false return is what callers act on.
        $payload = @file_get_contents($path);

        return ($payload !== false) ? PayloadSigner::verify($payload) : false;
    }

    /**
     * Read a reserved job's lease expiry, if any
     *
     * @param  string $reservedDir
     * @return ?int
     */
    protected function getLeaseUntil(string $reservedDir): ?int
    {
        $leaseFile = $reservedDir . DIRECTORY_SEPARATOR . 'lease';
        return file_exists($leaseFile) ? (int)file_get_contents($leaseFile) : null;
    }

    /**
     * Determine whether a reserved job's lease has expired. The directory's
     * own mtime is the primary signal - reserve() freshens it at claim time
     * (rename() itself doesn't update it), so a fresh claim's mtime alone is
     * sufficient to prove it isn't expired, regardless of what stale lease
     * content happens to still be sitting in the directory (a job that was
     * previously release()d or reclaimed carries its old, already-expired
     * lease file back into pending/ with it - nothing unlinks it, so the
     * next claim's directory starts out holding a stale expired lease next
     * to a brand new mtime). The lease file is only consulted to confirm
     * expiry once the mtime already looks stale, never to override a fresh
     * mtime.
     *
     * @param  string $dir
     * @param  int    $now
     * @return bool
     */
    protected function isLeaseExpired(string $dir, int $now): bool
    {
        $mtime = @filemtime($dir);
        if ($mtime === false) {
            return false;
        }

        $leaseUntil = $this->getLeaseUntil($dir);

        return ($mtime + $this->leaseSeconds <= $now) && (($leaseUntil === null) || ($leaseUntil <= $now));
    }

    /**
     * Move any reserved job whose lease has expired back to pending, so a
     * crashed worker's claim self-heals instead of being stuck forever.
     * Reclaimed jobs are eligible on this or a later reserve() call, not
     * necessarily returned by this one.
     *
     * Deliberately not throttled to one sweep per second, which would otherwise
     * look free: every input to the expiry decision has one-second resolution,
     * so two sweeps within the same second agree whenever the only thing moving
     * is the clock. That is not the only thing that can move. A lease can be
     * expired by writing to it, and a caller that does so and then calls
     * reserve() is entitled to see the reclaim happen on that call rather than
     * on whichever one lands in the next second. The sweep is cheap in any case,
     * because reserved/ only ever holds jobs currently in flight - it is bounded
     * by the number of live workers, not by queue depth.
     *
     * @return void
     */
    protected function reclaimExpiredLeases(): void
    {
        $now = time();

        foreach ($this->getFolders($this->reservedPath()) as $index) {
            $reservedDir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;

            if (!$this->isLeaseExpired($reservedDir, $now)) {
                continue;
            }

            // Stage through a uniquely-named path so at most one worker can win
            // the rename off this exact directory, and so we can re-verify what
            // actually got moved (not what we read a moment ago) before deciding
            // to reclaim it.
            $stagingDir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index . '.reclaim-' . getmypid() . '-' . bin2hex(random_bytes(4));
            if (!@rename($reservedDir, $stagingDir)) {
                // Lost the race - someone else is reclaiming or already re-claimed this index.
                continue;
            }

            // Re-check against what actually got staged (not the stale pre-read)
            // using the exact same lease-or-mtime rule, so this can't drift out
            // of sync with the check above and reclaim someone's fresh claim.
            if (!$this->isLeaseExpired($stagingDir, $now)) {
                // Whatever we staged turned out to be a fresh claim (another
                // worker reclaimed-and-re-reserved this same index between our
                // stale read and our rename winning) - put it back rather than
                // reclaim someone's active work.
                @rename($stagingDir, $reservedDir);
                continue;
            }

            $pendingDir = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;
            if (!@rename($stagingDir, $pendingDir)) {
                // Target collision or other failure - put it back to reserved/
                // rather than permanently orphaning it in a staging directory
                // nothing else will ever look at again.
                @rename($stagingDir, $reservedDir);
                continue;
            }
        }
    }

    /**
     * Find the reserved-job directory index holding a given job, if any
     *
     * Called by release(), delete() and bury() - so once per job a worker
     * finishes, whatever the outcome. It used to answer by reading and
     * unserializing every reserved payload in turn, which meant reconstructing
     * whole job objects (closures included) purely to read one string off each.
     *
     * reserve() now writes the claimed job's ID into a 'job-id' file beside the
     * payload, so the common path compares a short string read against a string,
     * and the payload is only unserialized for directories written before this
     * file existed - or by a reserve() that died between its rename() and its
     * sidecar write. Keeping that fallback is what makes the sidecar a pure
     * optimization: its absence costs speed, never correctness.
     *
     * @param  AbstractJob $job
     * @return ?int
     */
    protected function findReservedIndexForJob(AbstractJob $job): ?int
    {
        $jobId = $job->getJobId();

        foreach ($this->getFolders($this->reservedPath()) as $index) {
            // Skip staging directories (e.g. "5.reclaim-1234-abcd") left behind
            // mid-reclaim - (int) casting one of those resolves to a path that
            // doesn't actually exist, so treat only purely-numeric folder names
            // as real job slots.
            if (!ctype_digit((string)$index)) {
                continue;
            }

            $dir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;

            // Suppressed for the same reason as every other read on this path:
            // losing the file to a concurrent reclaim is ordinary operation.
            $storedId = @file_get_contents($dir . DIRECTORY_SEPARATOR . 'job-id');
            if ($storedId !== false) {
                if ($storedId === $jobId) {
                    return (int)$index;
                }
                continue;
            }

            $payloadFile = $dir . DIRECTORY_SEPARATOR . 'payload';
            if (file_exists($payloadFile)) {
                $raw = $this->readVerifiedPayload($payloadFile);
                // Suppressed: a corrupt/tampered payload makes unserialize() emit
                // a warning and return false, which the instanceof check below
                // handles.
                $stored = ($raw !== false) ? @unserialize($raw) : false;
                if (($stored instanceof AbstractJob) && ($stored->getJobId() === $jobId)) {
                    return (int)$index;
                }
            }
        }

        return null;
    }

    /**
     * Atomically claim a pending job directory by moving it into reserved/.
     * rename() is the whole claim: exactly one worker's rename off a given
     * source directory can succeed. Isolated into its own method (rather than
     * inlined in reserve()) purely so a test can deterministically simulate a
     * concurrent reclaim landing in the narrow window between this rename
     * winning and reserve()'s follow-up touch()/lease write - see the guard in
     * reserve() for what that window can otherwise damage.
     *
     * @param  string $pendingDir
     * @param  string $reservedDir
     * @return bool
     */
    protected function claimPendingDir(string $pendingDir, string $reservedDir): bool
    {
        return @rename($pendingDir, $reservedDir);
    }

    /**
     * Push job on to queue
     *
     * @param  AbstractJob $job
     * @throws Exception
     * @return File
     */
    public function push(AbstractJob $job): File
    {
        // Force job ID generation before persisting so identity survives the
        // serialize/unserialize round-trip on subsequent reserve/release/delete calls.
        $job->getJobId();

        // mkdir() itself is the atomic allocator: if two pushers compute the
        // same next index, only one mkdir() wins and the loser retries the
        // next index instead of silently clobbering the winner's payload. A
        // failed mkdir() only means "keep trying" when the collision is with
        // an existing directory (someone else's job) - any other failure
        // (permissions, disk full, pending/ missing) must not spin forever.
        //
        // getEndIndex() is only a hint now that it's cached per instance, so
        // this loop carries the two guards that hint can't provide on its own:
        //
        //  - A reserved/<index> check after the mkdir wins. An index is taken
        //    if a directory bearing it exists in *either* pending/ or reserved/,
        //    and mkdir() only knows about pending/. Checking after rather than
        //    before also closes the race where another worker reserves that
        //    index (moving it out of pending/ and into reserved/) in the gap
        //    between the two calls. Backing out is safe: the directory we
        //    created is still empty, and reserve() skips payload-less
        //    directories, so nothing can have claimed it in between.
        //
        //  - A single re-scan on the first collision. A stale hint means the
        //    real high-water mark has moved on, and walking up to it one
        //    mkdir() at a time would reintroduce exactly the per-push cost the
        //    cache exists to remove. Re-deriving it once jumps straight past
        //    every taken index; only if that *also* collides does this fall
        //    back to incrementing, which is the genuinely-contended case.
        $index     = $this->getEndIndex() + 1;
        $rescanned = false;

        while (true) {
            $dir = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;

            if (@mkdir($dir)) {
                if (!is_dir($this->reservedPath() . DIRECTORY_SEPARATOR . $index)) {
                    break;
                }
                @rmdir($dir);
            } else if (!is_dir($dir)) {
                throw new Exception('Error: Unable to create a new job folder in ' . $this->pendingPath() . '.');
            }

            if (!$rescanned) {
                $rescanned      = true;
                $this->endIndex = null;
                $index          = $this->getEndIndex() + 1;
            } else {
                $index++;
            }
        }

        $this->endIndex = $index;

        file_put_contents($dir . DIRECTORY_SEPARATOR . 'payload', PayloadSigner::sign(serialize(clone $job)));

        return $this;
    }

    /**
     * Atomically claim the next eligible job. Reclaims any reserved job whose
     * lease has expired first, then scans pending jobs in FIFO/FILO order,
     * skipping any that aren't yet available, and atomically claims the first
     * eligible one via rename() - if the rename fails, another worker won the
     * race and this moves on to the next candidate.
     *
     * @return ?AbstractJob
     */
    public function reserve(): ?AbstractJob
    {
        $this->reclaimExpiredLeases();

        // sort()/rsort() rather than usort() with a closure: the ordering is a
        // property of the queue, not of any pair of indices, so re-asking
        // isFifo() inside the comparator was doing O(n log n) method calls to
        // re-derive one value that cannot change mid-sort. These also sort ints
        // natively instead of calling back into PHP userland per comparison.
        $indices = array_map('intval', $this->getFolders($this->pendingPath()));

        if ($this->isFifo()) {
            sort($indices, SORT_NUMERIC);
        } else {
            rsort($indices, SORT_NUMERIC);
        }

        foreach ($indices as $index) {
            $pendingDir  = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;
            $payloadFile = $pendingDir . DIRECTORY_SEPARATOR . 'payload';

            if (!file_exists($payloadFile)) {
                continue;
            }

            // Suppressed: a corrupt/truncated payload makes unserialize() emit a
            // warning and return false, which the instanceof check below handles.
            // A payload that fails PayloadSigner::verify() (tampered, or written
            // by something other than this application when a signing key is
            // configured) is treated identically - $raw is false, so
            // unserialize() is never called on it at all.
            $raw = $this->readVerifiedPayload($payloadFile);
            $job = ($raw !== false) ? @unserialize($raw) : false;

            if (!($job instanceof AbstractJob)) {
                // Corrupt/tampered payload - skip rather than crash or claim garbage.
                continue;
            }

            if (!$job->isAvailable()) {
                continue;
            }

            $reservedDir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;
            if (!$this->claimPendingDir($pendingDir, $reservedDir)) {
                // Lost the race to another worker - move on.
                continue;
            }

            // A concurrent reclaim can move reserved/<index> away in the narrow
            // window between the rename() above winning and the touch() below.
            // Without this check, touch() on the now-vacant path would create a
            // stray *regular file* where a job directory is expected, making
            // that index permanently unclaimable (every future reserve() scan
            // reaches it, the claiming rename() fails against a file, so it is
            // skipped forever) and invisible to clear() (which only walks real
            // job directories). Bailing out here degrades that into an ordinary
            // lost race - the same safe failure mode every other race path in
            // this class has. It does not close the underlying window; encoding
            // the claim time into the rename itself is the real fix, deferred
            // to a future design pass.
            if (!is_dir($reservedDir)) {
                continue;
            }

            // Freshen mtime immediately: rename() doesn't update it, so without
            // this the directory's mtime would still reflect when the job was
            // originally pushed, breaking isLeaseExpired()'s mtime fallback for
            // any job that sat pending longer than the lease window before
            // being claimed.
            touch($reservedDir);
            file_put_contents($reservedDir . DIRECTORY_SEPARATOR . 'lease', (string)(time() + $this->leaseSeconds));

            // Sidecar for findReservedIndexForJob(), which release()/delete()/
            // bury() all go through when this job finishes. Written after the
            // lease so it can never be the thing that makes a claim look
            // complete before it is; a failure to write it costs nothing but
            // the fast path, since that lookup falls back to the payload.
            file_put_contents($reservedDir . DIRECTORY_SEPARATOR . 'job-id', $job->getJobId());

            return $job;
        }

        return null;
    }

    /**
     * Put a job back to pending, honoring its backoff schedule unless an
     * explicit delay is given
     *
     * @param  AbstractJob $job
     * @param  ?int        $delay
     * @return File
     */
    public function release(AbstractJob $job, ?int $delay = null): File
    {
        $index = $this->findReservedIndexForJob($job);
        if ($index === null) {
            return $this;
        }

        $job->delay($delay ?? $job->getBackoffDelay());

        $reservedDir = $this->reservedPath() . DIRECTORY_SEPARATOR . $index;
        $pendingDir  = $this->pendingPath() . DIRECTORY_SEPARATOR . $index;

        file_put_contents($reservedDir . DIRECTORY_SEPARATOR . 'payload', PayloadSigner::sign(serialize(clone $job)));
        rename($reservedDir, $pendingDir);

        return $this;
    }

    /**
     * Permanently remove a job
     *
     * @param  AbstractJob $job
     * @return File
     */
    public function delete(AbstractJob $job): File
    {
        $index = $this->findReservedIndexForJob($job);
        if ($index === null) {
            return $this;
        }

        $this->removeJobDir($this->reservedPath() . DIRECTORY_SEPARATOR . $index);

        return $this;
    }

    /**
     * Empty and remove a job directory.
     *
     * Deliberately generic rather than unlinking 'payload' and 'lease' by name:
     * rmdir() fails on a non-empty directory, so every file a job directory can
     * hold has to be accounted for here, and naming them individually means any
     * future addition silently turns delete() into a no-op that leaves the
     * directory behind. Clearing whatever is actually in there cannot drift out
     * of sync that way.
     *
     * @param  string $dir
     * @return void
     */
    protected function removeJobDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        // Iterated raw rather than through getFiles(), which filters out
        // '.empty' - anything left behind, whatever its name, keeps rmdir()
        // from succeeding.
        foreach (new \FilesystemIterator($dir, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isFile()) {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($dir);
    }

    /**
     * Move a job to the dead-letter store
     *
     * @param  AbstractJob $job
     * @param  ?string     $reason
     * @return File
     */
    public function bury(AbstractJob $job, ?string $reason = null): File
    {
        $this->delete($job);
        file_put_contents($this->folder . DIRECTORY_SEPARATOR . 'dead-' . $job->getJobId(), PayloadSigner::sign(serialize(clone $job)));

        return $this;
    }

    /**
     * Check if adapter has jobs
     *
     * @return bool
     */
    public function hasJobs(): bool
    {
        return ($this->count() > 0);
    }

    /**
     * Count of pending + reserved jobs
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->getFolders($this->pendingPath())) + count($this->getFolders($this->reservedPath()));
    }

    /**
     * Clear pending and reserved jobs (not tasks or dead-letter jobs)
     *
     * @return File
     */
    public function clear(): File
    {
        foreach ([$this->pendingPath(), $this->reservedPath()] as $path) {
            foreach ($this->getFolders($path) as $index) {
                $this->removeJobDir($path . DIRECTORY_SEPARATOR . $index);
            }
        }

        // Both directories are empty now, so the cached high-water index is
        // stale in the one direction worth correcting: leaving it set would
        // keep numbering new jobs from wherever the cleared queue left off,
        // where a fresh scan restarts from 1 the way it always has.
        $this->endIndex = null;

        return $this;
    }

    /**
     * Get the dead-letter job IDs
     *
     * @return array
     */
    protected function getDeadJobIds(): array
    {
        $ids = [];
        foreach ($this->getFiles($this->folder) as $file) {
            if (str_starts_with($file, 'dead-')) {
                $ids[] = substr($file, 5);
            }
        }

        return $ids;
    }

    /**
     * Check if adapter has dead-letter jobs
     *
     * @return bool
     */
    public function hasDeadJobs(): bool
    {
        return !empty($this->getDeadJobIds());
    }

    /**
     * Count of dead-letter jobs
     *
     * @return int
     */
    public function countDead(): int
    {
        return count($this->getDeadJobIds());
    }

    /**
     * Get dead-letter jobs
     *
     * @param  bool $unserialize
     * @return array
     */
    public function getDeadJobs(bool $unserialize = true): array
    {
        $jobs = [];
        foreach ($this->getDeadJobIds() as $jobId) {
            $jobs[$jobId] = $this->getDeadJob($jobId, $unserialize);
        }

        return $jobs;
    }

    /**
     * Get a dead-letter job
     *
     * @param  string $jobId
     * @param  bool   $unserialize
     * @return mixed
     */
    public function getDeadJob(string $jobId, bool $unserialize = true): mixed
    {
        $path = $this->folder . DIRECTORY_SEPARATOR . 'dead-' . $jobId;
        if (!file_exists($path)) {
            return null;
        }

        // Read once and share it with both branches rather than going through
        // readVerifiedPayload(), which would re-read the file for the
        // unserialize case. A failed read is reported the same way a missing
        // file is, above - the job is unreadable either way.
        $payload = @file_get_contents($path);
        if ($payload === false) {
            return null;
        }

        if (!$unserialize) {
            return $payload;
        }

        $raw = PayloadSigner::verify($payload);
        return ($raw !== false) ? unserialize($raw) : false;
    }

    /**
     * Move a dead-letter job back to pending
     *
     * @param  string $jobId
     * @return File
     */
    public function retryDeadJob(string $jobId): File
    {
        $job = $this->getDeadJob($jobId);
        if ($job instanceof AbstractJob) {
            $this->push($job);
            $this->deleteDeadJob($jobId);
        }

        return $this;
    }

    /**
     * Permanently remove a dead-letter job
     *
     * @param  string $jobId
     * @return File
     */
    public function deleteDeadJob(string $jobId): File
    {
        $path = $this->folder . DIRECTORY_SEPARATOR . 'dead-' . $jobId;
        if (file_exists($path)) {
            unlink($path);
        }

        return $this;
    }

    /**
     * Clear all dead-letter jobs
     *
     * @return File
     */
    public function clearDead(): File
    {
        foreach ($this->getDeadJobIds() as $jobId) {
            $this->deleteDeadJob($jobId);
        }

        return $this;
    }

    /**
     * Schedule job with queue
     *
     * @param  Task $task
     * @return File
     */
    public function schedule(Task $task): File
    {
        if ($task->isValid()) {
            file_put_contents(
                $this->folder . DIRECTORY_SEPARATOR . 'task-' . $task->getJobId(), PayloadSigner::sign(serialize(clone $task))
            );
        }
        return $this;
    }

    /**
     * Get scheduled tasks
     *
     * @return array
     */
    public function getTasks(): array
    {
        $files = $this->getFiles($this->folder);
        $tasks = [];

        foreach ($files as $file) {
            if (str_starts_with($file, 'task-')) {
                $tasks[] = substr($file, 5);
            }
        }

        return $tasks;
    }

    /**
     * Get scheduled task
     *
     * @param  string $taskId
     * @return ?Task
     */
    public function getTask(string $taskId): ?Task
    {
        $path = $this->folder . DIRECTORY_SEPARATOR . 'task-' . $taskId;
        if (!file_exists($path)) {
            return null;
        }

        // Guarded with instanceof rather than returning unserialize()'s result
        // directly, the same way reserve() does: a corrupt, truncated or
        // tampered payload makes unserialize() return false, and false out of
        // a ": ?Task" method is a TypeError, not a null. A bad task file should
        // read as "no such task", never as a crash.
        $raw  = $this->readVerifiedPayload($path);
        $task = ($raw !== false) ? @unserialize($raw) : false;

        return ($task instanceof Task) ? $task : null;
    }

    /**
     * Update scheduled task
     *
     * @param  Task $task
     * @return File
     */
    public function updateTask(Task $task): File
    {
        if ($task->isValid()) {
            file_put_contents(
                $this->folder . DIRECTORY_SEPARATOR . 'task-' . $task->getJobId(), PayloadSigner::sign(serialize(clone $task))
            );
        } else {
            $this->removeTask($task->getJobId());
        }

        return $this;
    }

    /**
     * Remove scheduled task
     *
     * @param  string $taskId
     * @return File
     */
    public function removeTask(string $taskId): File
    {
        if (file_exists($this->folder . DIRECTORY_SEPARATOR . 'task-' . $taskId)) {
            unlink($this->folder . DIRECTORY_SEPARATOR . 'task-' . $taskId);
        }
        if (file_exists($this->taskClaimPath($taskId))) {
            unlink($this->taskClaimPath($taskId));
        }
        return $this;
    }

    /**
     * Get scheduled tasks count
     *
     * @return int
     */
    public function getTaskCount(): int
    {
        return count($this->getTasks());
    }

    /**
     * Has scheduled tasks
     *
     * @return bool
     */
    public function hasTasks(): bool
    {
        return ($this->getTaskCount() > 0);
    }

    /**
     * Clear all scheduled task
     *
     * @return File
     */
    public function clearTasks(): File
    {
        $tasks = $this->getTasks();

        foreach ($tasks as $taskId) {
            $this->removeTask($taskId);
        }

        foreach ($this->getFiles($this->folder) as $file) {
            if (str_starts_with($file, 'claim-task-')) {
                unlink($this->folder . DIRECTORY_SEPARATOR . $file);
            }
        }

        return $this;
    }

    /**
     * Get the claim-marker file path for a task
     *
     * @param  string $taskId
     * @return string
     */
    protected function taskClaimPath(string $taskId): string
    {
        return $this->folder . DIRECTORY_SEPARATOR . 'claim-task-' . $taskId;
    }

    /**
     * Atomically claim a task's current due-window via a small sidecar
     * file, deliberately separate from the task's own serialized
     * definition file (task-<taskId>) so a claim attempt never touches or
     * re-serializes the closure-bearing Task object. Content format is
     * "<window>:<expiresAtUnixTimestamp>". flock() provides real
     * cross-process mutual exclusion for the read-decide-write.
     *
     * @param  string $taskId
     * @param  string $window
     * @return bool
     */
    public function claimTaskRun(string $taskId, string $window): bool
    {
        $path = $this->taskClaimPath($taskId);

        $fh = fopen($path, 'c+');
        if ($fh === false) {
            return false;
        }

        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            return false;
        }

        $contents = stream_get_contents($fh);
        $now      = time();
        $claimed  = true;

        if (!empty($contents)) {
            [$storedWindow, $storedExpiry] = array_pad(explode(':', $contents, 2), 2, '0');
            if (($storedWindow === $window) && ((int)$storedExpiry > $now)) {
                $claimed = false;
            }
        }

        if ($claimed) {
            ftruncate($fh, 0);
            rewind($fh);
            fwrite($fh, $window . ':' . ($now + self::TASK_CLAIM_TTL));
            fflush($fh);
        }

        flock($fh, LOCK_UN);
        fclose($fh);

        return $claimed;
    }

    /**
     * Get folders
     *
     * @param  string $folder
     * @return array
     */
    public function getFolders(string $folder): array
    {
        return $this->readDirectory($folder, true);
    }

    /**
     * Get files from folder
     *
     * @param  string $folder
     * @return array
     */
    public function getFiles(string $folder): array
    {
        return $this->readDirectory($folder, false);
    }

    /**
     * List the entries of a directory, keeping either the subdirectories or the
     * plain files.
     *
     * Both public listers route through here rather than each running their own
     * scandir(). Two things made that pairing expensive on the hot paths -
     * reserve() and count() call it on every invocation, once per pending or
     * reserved job:
     *
     *  - scandir() returns names only, so deciding what each entry *is* meant an
     *    is_dir() stat syscall per entry. FilesystemIterator carries the type
     *    along with the directory read, so isDir() answers from what the OS
     *    already handed back.
     *  - scandir() sorts alphabetically by default, and every caller here either
     *    wants numeric order (reserve() re-sorts these index names itself) or no
     *    order at all, so that sort was pure waste.
     *
     * A missing directory reads as empty rather than raising, matching the
     * is_dir() guard both listers carried before.
     *
     * @param  string $folder
     * @param  bool   $directories
     * @return array
     */
    protected function readDirectory(string $folder, bool $directories): array
    {
        if (!is_dir($folder)) {
            return [];
        }

        $entries = [];

        // SKIP_DOTS drops '.' and '..'; '.empty' is a repo placeholder that is
        // neither a job nor a task and has always been filtered out here.
        $iterator = new \FilesystemIterator($folder, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_FILEINFO);

        foreach ($iterator as $entry) {
            $name = $entry->getFilename();
            if (($name !== '.empty') && ($entry->isDir() === $directories)) {
                $entries[] = $name;
            }
        }

        return $entries;
    }

}
