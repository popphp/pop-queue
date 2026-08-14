<?php

namespace Pop\Queue\Test\Process;

use Pop\Application;
use Pop\Queue\Process\Job;
use PHPUnit\Framework\TestCase;
use Pop\Queue\Test\TestAsset\BoomCommand;
use Pop\Queue\Test\TestAsset\ContextCommand;
use Pop\Queue\Test\TestAsset\GreetCommand;
use Pop\Queue\Test\TestAsset\NotifyCommand;
use Pop\Queue\Test\TestAsset\SloppyBufferCommand;
use Pop\Queue\Test\TestAsset\ZeroOutputCommand;

/**
 * Covers running an application command as a job - the Job::command() path
 * through Pop\Application's router, as opposed to Job::exec()'s shell path.
 */
class JobCommandTest extends TestCase
{

    protected function createApplication(): Application
    {
        return new Application([
            'routes' => [
                'greet <name>'    => ['controller' => GreetCommand::class,      'action' => 'handle'],
                'notify <message>'=> ['controller' => NotifyCommand::class,     'action' => 'handle'],
                'boom'            => ['controller' => BoomCommand::class,       'action' => 'handle'],
                'zero'            => ['controller' => ZeroOutputCommand::class, 'action' => 'handle'],
                'hello'           => function() { echo 'Hello World!'; },
            ]
        ]);
    }

    /**
     * The whole point of queueing a command: invoking it the way you would
     * type it, with real argument values - not by repeating the route's
     * definition string back at the router.
     */
    public function testRunCommandWithParameterizedInvocation()
    {
        $job = Job::command('greet Nick');
        $this->assertEquals(['Hello Nick'], $job->run($this->createApplication()));
    }

    /**
     * Application::router() is nullable - Application::__unset() will null it
     * - and a command job handed a router-less application must decline
     * rather than fatal on null.
     */
    public function testRunCommandWithRouterlessApplicationReturnsFalse()
    {
        $app = $this->createApplication();
        unset($app->router);
        $this->assertNull($app->router());

        $job = Job::command('greet Nick');
        $this->assertFalse($job->run($app));
    }

    public function testRunCommandWithNoMatchingRouteReturnsFalse()
    {
        $job = Job::command('nonexistent-command');
        $this->assertFalse($job->run($this->createApplication()));
    }

    /**
     * runCommand() opens an output buffer to capture the command's output.
     * A command that throws must not leave that buffer open - in a worker
     * daemon a leaked buffer is never reclaimed, so it both grows without
     * bound and silently swallows everything printed afterwards.
     */
    public function testRunCommandRestoresOutputBufferWhenCommandThrows()
    {
        $job            = Job::command('boom');
        $level          = ob_get_level();
        $levelAfterFail = null;

        try {
            $job->run($this->createApplication());
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            // Sample the level *before* any cleanup of our own, otherwise
            // this test would tidy up the very leak it exists to catch.
            $levelAfterFail = ob_get_level();
            $this->assertEquals('kaboom', $e->getMessage());
        } finally {
            // Recover the buffer regardless, so a leak here cannot swallow
            // the rest of the suite's output.
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
        }

        $this->assertEquals($level, $levelAfterFail);
    }

    /**
     * Mirrors testRunExecPreservesLiteralZeroOutputLine - a line consisting
     * of "0" is real output, not an empty line, and the two paths must
     * agree on that.
     */
    /**
     * A command that opens a buffer and forgets to close it is a bug in the
     * command, but the worker must not inherit it - runCommand() unwinds
     * back to the level it started at rather than closing exactly one.
     */
    public function testRunCommandUnwindsBuffersLeftOpenByTheCommand()
    {
        $app = $this->createApplication();
        $app->router()->addRoute('sloppy', ['controller' => SloppyBufferCommand::class, 'action' => 'handle']);

        $job   = Job::command('sloppy');
        $level = ob_get_level();

        $results = $job->run($app);

        $this->assertEquals($level, ob_get_level());
        $this->assertEquals(['captured', 'stranded'], $results);
    }

    public function testRunCommandPreservesLiteralZeroOutputLine()
    {
        $job = Job::command('zero');
        $this->assertEquals(['alpha', '0', 'bravo'], $job->run($this->createApplication()));
    }

    public function testRunCommandResultsAreSequentiallyIndexed()
    {
        $job     = Job::command('zero');
        $results = $job->run($this->createApplication());

        $this->assertSame(range(0, count($results) - 1), array_keys($results));
    }

    public function testRunCommandStillSupportsClosureRoutes()
    {
        $job = Job::command('hello');
        $this->assertEquals(['Hello World!'], $job->run($this->createApplication()));
    }

    /**
     * A string command is split on whitespace, so a value containing spaces
     * cannot survive it. The argv-style array form addresses each segment
     * explicitly, the same way Job::exec() does.
     */
    public function testCommandAcceptsArrayForm()
    {
        $job = Job::command(['notify', 'Hello there, world']);

        $this->assertTrue($job->hasCommand());
        $this->assertEquals(['notify', 'Hello there, world'], $job->getCommand());
    }

    public function testRunCommandArrayPreservesValueContainingSpaces()
    {
        $job = Job::command(['notify', 'Hello there, world']);
        $this->assertEquals(['MSG=[Hello there, world]'], $job->run($this->createApplication()));
    }

    public function testRunCommandStringFormTruncatesValueAtFirstSpace()
    {
        // Documents *why* the array form exists: the string form is split on
        // whitespace, so only the first word reaches the command.
        $job = Job::command('notify Hello there, world');
        $this->assertEquals(['MSG=[Hello]'], $job->run($this->createApplication()));
    }

    /**
     * A queued command job carries no description of its own, but the
     * command class already documents itself - reuse that so a job is
     * identifiable in the queue registry.
     */
    public function testRunCommandSetsJobDescriptionFromCommandHelp()
    {
        $job = Job::command('greet Nick');
        $job->run($this->createApplication());

        $this->assertTrue($job->hasJobDescription());
        $this->assertEquals('Greets somebody by name', $job->getJobDescription());
    }

    public function testRunCommandDoesNotOverwriteExplicitJobDescription()
    {
        $job = Job::command('greet Nick');
        $job->setJobDescription('Nightly greeting run');
        $job->run($this->createApplication());

        $this->assertEquals('Nightly greeting run', $job->getJobDescription());
    }

    public function testRunCommandLeavesDescriptionUnsetForClosureRoute()
    {
        // A closure has no name or help to borrow, so nothing is invented.
        $job = Job::command('hello');
        $job->run($this->createApplication());

        $this->assertFalse($job->hasJobDescription());
    }

    /**
     * Pins the behavior the popphp/pop-console dispatch refactor delivered:
     * a command run as a job is constructed with the Application, and gets
     * a Console, so $this->console() is usable inside a queued command.
     */
    public function testRunCommandInjectsApplicationAndConsoleIntoCommand()
    {
        $app = $this->createApplication();
        $app->router()->addRoute('context', ['controller' => ContextCommand::class, 'action' => 'handle']);

        $job = Job::command('context');
        $this->assertEquals(['application:true', 'console:true'], $job->run($app));
    }

}
