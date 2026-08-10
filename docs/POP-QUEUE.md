# POP-QUEUE.md

Internal mechanics of `pop-queue` (`Pop\Queue`), for anyone modifying the component rather than just consuming
it. **README.md** is the user-facing API reference — read that first for usage. This document covers the
"how it actually works" underneath: adapter storage formats, status-code semantics, the worker's execution
loop, job serialization, and the cron evaluation engine. Nothing here is specific to any one Pop application;
this is the queue component itself.

## Mental model

Three layers, each in its own namespace:

- **`Pop\Queue\Adapter`** — pluggable storage. Knows how to push/pop serialized job payloads and
  (for adapters that support it) persist scheduled tasks. No knowledge of *how* a job runs.
- **`Pop\Queue\Process`** — what gets stored and executed: `Job`/`Task` objects carrying a callable/command/
  exec target plus lifecycle state (attempts, timestamps, failure messages), and `Cron` for schedule
  evaluation. No knowledge of storage.
- **`Pop\Queue`** (`Queue`, `Worker`) — the glue. `Queue` pairs a name with one adapter and drives the
  pop-run-complete/fail cycle; `Worker` fans that out across multiple named queues.

A job/task is `serialize()`d (or `base64_encode(serialize())`'d, depending on the adapter's storage medium)
on push and `unserialize()`d on pop/read — the adapters are dumb byte stores, all the interesting behavior
(retry, expiry, validity) lives on the `Process` object itself and is re-evaluated by `Queue`/adapter code
every time a payload round-trips through storage.

## Adapter contract

`AdapterInterface` (jobs only) is implemented by `AbstractAdapter`. `TaskAdapterInterface` (adds scheduled
tasks) is implemented by `AbstractTaskAdapter extends AbstractAdapter`. Four concrete adapters:

| Adapter    | Base class            | Supports tasks | Backing store |
|------------|------------------------|:--:|---|
| `File`     | `AbstractTaskAdapter`  | ✓ | one directory per job index, on disk |
| `Database` | `AbstractTaskAdapter`  | ✓ | one row per job/task, via `pop-db` |
| `Redis`    | `AbstractTaskAdapter`  | ✓ | Redis list (jobs) + string keys (tasks) |
| `Sqs`      | `AbstractAdapter`      | ✗ | AWS SQS queue |

`Queue::addTask()` type-checks the adapter against `TaskAdapterInterface` and throws
`Pop\Queue\Exception` if it doesn't qualify — that's the only place the Sqs task restriction is enforced;
nothing stops you from constructing a `Task` against an `Sqs` adapter, it just can't be scheduled.

### Job lifecycle states

A job in File/Database/Redis moves through three states, tracked as adapter-side storage
metadata (never as a flag on the job object itself — see `Queue::work()` below):

- **pending** — pushed, eligible for `reserve()` once `availableAt` (if any) has passed.
- **reserved** — claimed by a `reserve()` call, with a lease (`reservedUntil`, default 60s). If
  the worker that reserved it dies before calling `delete()`/`release()`/`bury()`, the lease
  simply expires and the job becomes eligible for `reserve()` again — no separate cleanup process
  needed. This is what prevents permanent job loss on a worker crash.
- **dead** — permanently buried via `bury()` (job exhausted `maxAttempts`, expired, or the caller
  gave up on it explicitly). Stored durably and separately from pending/reserved jobs, inspectable
  via `getDeadJobs()`/`getDeadJob()` and recoverable via `retryDeadJob()`.

`Sqs` doesn't track index-addressable status at all — its "reserved" state *is* SQS's own native
`VisibilityTimeout` mechanism, and it has no client-side way to support dead-letter introspection
(`getDeadJobs()` etc. are honest no-ops/throws there — use a native SQS redrive policy on a
separate dead-letter queue instead).

**Note on the real adapters' current maturity:** File, Database, and Redis satisfy this state
model using their existing (non-atomic) storage primitives — `reserve()` there is not yet safe
under concurrent workers (a race between two workers' `reserve()` calls can still double-claim a
job). `File::reserve()` checks `$job->isAvailable()` before returning a job as eligible, so
`delay()`/`setBackoff()` *are* honored there — a delayed or backed-off job stays ineligible until
its `availableAt` passes. `Database::reserve()` and `Redis::reserve()` don't yet perform that
check, so on those two adapters a delayed or backed-off job is still immediately eligible again,
same as if no delay/backoff were set (tracked as follow-up cleanup, not part of this phase). Only
`Sqs` is genuinely atomic today, via its native visibility timeout; `Memory` implements the full
model correctly (atomic reserve *and* `availableAt` honored) and is the best adapter to test
delay/backoff/lease behavior against in the meantime. Making File/Database/Redis's `reserve()`
atomic (POSIX `rename()`, a conditional `UPDATE`, an atomic `LMOVE`, respectively), and bringing
Database/Redis's `reserve()` up to parity with File's `availableAt` check, is the subject of the
next phase of this component's roadmap.

### FIFO/FILO mechanics per adapter

`AbstractAdapter::isFifo()/isFilo()` just compares `$priority` against the `Queue::FIFO`/`Queue::FILO`
constants — the actual push/pop direction is reimplemented per adapter since each storage medium indexes
differently:

- **File / Database** — jobs occupy an integer `index` (folder name / `index` column) that increments from
  `getEnd() + 1` on push. FIFO pops from `getStart()` (lowest index), FILO pops from `getEnd()` (highest).
  A *failed* job re-pushed under FILO priority is instead re-inserted at `getStart() - 1`, i.e. pushed to the
  front rather than the back — so under FILO, a failing job gets retried before newer jobs, not after.
- **Redis** — a single Redis list keyed by the prefix, plus a parallel `<prefix>:status` list of matching
  status ints (index-aligned with the job list). FIFO pushes with `lPush` (prepend) and pops from the tail;
  FILO pushes failed jobs with `rPush` (append) and pops from the head — again, a failed job jumps toward the
  front of a FILO queue rather than to the back.
- **Sqs** — priority is not user-settable; it's derived once from the queue URL in the constructor
  (`str_ends_with($queueUrl, '.fifo') ? Queue::FIFO : Queue::FILO`) and ordering itself is left entirely to
  SQS's own semantics (`MessageGroupId` is only sent for FIFO queues). `getStart()` is hardcoded to `0` and
  `getStatus()` is hardcoded to `0` — index-based addressing doesn't apply to SQS at all.

### Task storage per adapter

- **File** — one `task-<jobId>` file per task, containing the serialized `Task`.
- **Database** — same table as jobs, disambiguated by a `type` column (`'job'` vs `'task'`); tasks don't use
  the `index` column (it's `nullable` specifically for this).
- **Redis** — one `<prefix>:task-<jobId>` string key per task. `getTasks()`/`hasTasks()`/`clearTasks()` all
  do a `KEYS <prefix>:task-*` scan — fine for moderate task counts, but worth knowing this isn't backed by a
  dedicated set/index.

## Queue / Worker execution loop

`Queue::work()`: `adapter->reserve()` → if nothing was eligible, return `null`. If the reserved
job is already invalid (expired, or exhausted `maxAttempts` before ever running — possible if it
sat in queue past its `runUntil`), it's `bury()`d immediately without executing. Otherwise it
runs (wrapped in a soft `pcntl`-based timeout when the job has one and `ext-pcntl` is loaded) and:
on success, `complete()` then `delete()` (permanent removal); on any `\Throwable` (not just
`\Exception` — an `Error` inside a job is now recorded as a failure instead of crashing the
worker), `failed($message)` then either `release()` (if still valid — attempts remain, not
expired) or `bury()` (if not). `release()` computes the next `availableAt` from the job's
`getBackoffDelay()` unless an explicit delay is passed.

`Queue::run()` (task scheduling) walks every task ID from `adapter->getTasks()`, loads the full `Task`, and
branches on whether its `Cron` has a seconds field (`$task->cron()->hasSeconds()`):

- **Sub-minute (has seconds)** — busy-loops for up to 60 iterations, `sleep(1)` between each, re-evaluating
  `cron()->evaluate()` every second and re-running the task each time it matches. This means a queue with any
  sub-second-precision task makes `Queue::run()` block for roughly 60 seconds per call — it's designed to be
  the single per-minute cron invocation, not something safe to call in a tight loop itself. On success it
  calls `$task->complete()` but does **not** write back to the adapter (no `updateTask()`/`schedule()` call in
  this branch); on failure it does `removeTask()` + `schedule()` (a re-insert cycle) to persist the failure
  state.
- **Minute-or-coarser (no seconds)** — evaluates once, runs once if due, and persists either way via
  `adapter->updateTask()` (both the success and failure paths call it, unlike the sub-minute branch).

`Worker` is a thin multi-`Queue` router (`work(name)`/`workAll()`, `run(name)`/`runAll()`,
`clear*(name)`/`clearAll*()`) that also threads an optional `Pop\Application` through to every `Queue::work()`/
`run()` call it makes, plus `ArrayAccess`/magic-property access to registered queues (`$worker['name']` /
`$worker->name`).

## Job/Task lifecycle (`Process\AbstractJob`)

State tracked per job: `id` (lazily generated `sha1(uniqid(rand()) . time())` on first `getJobId()` call if
never set), one of `callable` / application `command` / CLI `exec` as the executable target, `started` /
`completed` / `failed` unix timestamps, `attempts` vs `maxAttempts` (`0` = unlimited), `runUntil`
(timestamp or date string), and `failedMessages` (keyed by the failure timestamp, so multiple failures across
retries all get recorded rather than overwriting each other).

`isValid()` = `!isExpired() && !hasExceededMaxAttempts()`. Both checks matter for both job types, not just
their "obvious" case — the README calls this out too: `runUntil` (normally a task concept) can be set on a
plain job, and `maxAttempts` (normally a job concept) can limit a task's total run count.

`run()` dispatches to exactly one of `loadCallable()` / `runCommand()` / `runExec()` based on which of
`callable`/`command`/`exec` is set (checked in that order) — `command` additionally requires an
`Application` to be passed in, since it needs the app's router to resolve the route.

### Closure serialization

Job payloads are `serialize()`d for storage, but PHP closures aren't natively serializable. `AbstractJob`
handles this in `__sleep()`/`__wakeup()`: if the callable wraps a `Closure`, `__sleep()` wraps it in a
`Laravel\SerializableClosure\SerializableClosure`, serializes *that*, stashes the result in
`$serializedClosure`/`$serializedParameters`, and nulls out `$callable` (so the outer `serialize()` doesn't
choke on the raw closure). `__wakeup()` reverses this. Note the sub-minute task loop in `Queue::run()`
explicitly calls `$task->__wakeup()` before each re-run — that loop keeps reusing the same in-memory `Task`
object across iterations rather than re-fetching from the adapter, so it has to manually re-hydrate the
closure each pass rather than relying on a fresh `unserialize()`.

## Cron engine (`Process\Cron`)

Six schedule fields tracked as arrays: `seconds`, `minutes`, `hours`, `daysOfTheMonth`, `months`,
`daysOfTheWeek`. Standard cron only has the last five; `seconds` is this component's own extension,
distinguished by `hasSeconds()` and only populated when a 6-field schedule string is given (or one of the
`every*Seconds()` helpers is used) — this is what `Queue::run()` checks to decide whether a task needs the
sub-minute busy-loop treatment.

`evaluate(mixed $time = null, ?int $buffer = null)`: for each field, either an exact `in_array($value,
$field)` match, a literal `'*'` (any), or — if the field holds a single string expression instead of an int
array — one of three cron expression forms handled by `evaluateExpression()`:

- `a,b,c` → membership list
- `n-m` → inclusive range
- `*/n` → step (`$value % $n == 0`)

`buffer` only matters for schedules **without** a seconds field: after all the minute/hour/day/month/dow
checks pass, the actual second-of-the-minute must additionally satisfy `$buffer < 0 || $second <= $buffer`.
So `buffer` widens the window of seconds within the matched minute during which a coarse (non-sub-minute)
schedule is considered "due" — the default `0` means only second `:00` counts as due, matching strict cron
semantics; `-1` means any second within the matched minute counts (unconditionally due once the minute
arrives, useful if the caller can't guarantee invocation lands exactly on `:00`).

## Testing notes

- `tests/Adapter/{FileTest,DatabaseTest,RedisTest}.php` cover the three task-capable adapters; there is no
  `SqsTest` (would require live AWS infrastructure to exercise `SqsClient` calls).
- `RedisTest` needs a real Redis server reachable at `localhost:6379`; CI provisions one as a service
  container (`.github/workflows/phpunit.yml`).
- `DatabaseTest` needs a working `pop-db` connection; the adapter auto-creates its table on first
  construction if missing (`Database::createTable()`), so tests don't need to pre-seed schema.
- `tests/Process/{JobTest,TaskTest,CronTest}.php` cover lifecycle state and schedule evaluation without
  needing any storage adapter at all — prefer adding coverage here for anything that's really a `Process`
  concern (attempts, expiry, cron matching) rather than duplicating it per-adapter.
