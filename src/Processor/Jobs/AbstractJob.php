<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor\Jobs;

use Pop\Queue\Processor\AbstractProcessor;
use Pop\Application;
use SuperClosure\Serializer;

/**
 * Abstract job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
abstract class AbstractJob implements JobInterface
{

    /**
     * Job callable
     * @var mixed
     */
    protected $callable = null;

    /**
     * Job parameters
     * @var mixed
     */
    protected $params = null;

    /**
     * Job application command
     * @var string
     */
    protected $command = null;

    /**
     * Job CLI executable command
     * @var string
     */
    protected $exec = null;

    /**
     * Job ID
     * @var string
     */
    protected $id = null;

    /**
     * The processor the job belongs to (Worker or Scheduler)
     * @var AbstractProcessor
     */
    protected $processor = null;

    /**
     * Job running flag
     * @var boolean
     */
    protected $running = false;

    /**
     * Job completed flag
     * @var boolean
     */
    protected $completed = false;

    /**
     * Job failed flag
     * @var boolean
     */
    protected $failed = false;

    /**
     * Attempt once flag
     * @var boolean
     */
    protected $attemptOnce = false;

    /**
     * Serialize callable
     * @var mixed
     */
    protected $serializedCallable = null;

    /**
     * Constructor
     *
     * Instantiate the job object
     *
     * @param  mixed             $callable
     * @param  mixed             $params
     * @param  AbstractProcessor $processor
     * @param  string            $id
     */
    public function __construct($callable = null, $params = null, AbstractProcessor $processor = null, $id = null)
    {
        if (null !== $callable) {
            $this->setCallable($callable, $params);
        }
        if (null !== $processor) {
            $this->setProcessor($processor);
        }
        if (null !== $id) {
            $this->setJobId($id);
        }
    }

    /**
     * Set job callable
     *
     * @param  mixed $callable
     * @param  mixed $params
     * @return AbstractJob
     */
    public function setCallable($callable, $params = null)
    {
        $this->callable = $callable;
        $this->params   = $params;

        return $this;
    }

    /**
     * Set job application command
     *
     * @param  string $command
     * @return AbstractJob
     */
    public function setCommand($command)
    {
        $this->command = $command;
        return $this;
    }

    /**
     * Set job CLI executable command
     *
     * @param  string executable
     * @return AbstractJob
     */
    public function setExec($command)
    {
        $this->exec = $command;
        return $this;
    }

    /**
     * Get job callable
     *
     * @return mixed
     */
    public function getCallable()
    {
        return $this->callable;
    }

    /**
     * Get job application command
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * Get job CLI executable command
     *
     * @return string
     */
    public function getExec()
    {
        return $this->exec;
    }

    /**
     * Has job callable
     *
     * @return boolean
     */
    public function hasCallable()
    {
        return (null !== $this->callable);
    }

    /**
     * Has job application command
     *
     * @return boolean
     */
    public function hasCommand()
    {
        return (null !== $this->command);
    }

    /**
     * Has job CLI executable command
     *
     * @return boolean
     */
    public function hasExec()
    {
        return (null !== $this->exec);
    }

    /**
     * Set job ID
     *
     * @param  string $id
     * @return AbstractJob
     */
    public function setJobId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get job ID
     *
     * @return string
     */
    public function getJobId()
    {
        return $this->id;
    }

    /**
     * Has job ID
     *
     * @return boolean
     */
    public function hasJobId()
    {
        return (null !== $this->id);
    }

    /**
     * Set processor
     *
     * @param  AbstractProcessor $processor
     * @return AbstractJob
     */
    public function setProcessor(AbstractProcessor $processor)
    {
        $this->processor = $processor;
        return $this;
    }

    /**
     * Get processor
     *
     * @return AbstractProcessor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Has processor
     *
     * @return boolean
     */
    public function hasProcessor()
    {
        return (null !== $this->processor);
    }

    /**
     * Set job to only attempt once
     *
     * @param  boolean $run
     * @return JobInterface
     */
    public function attemptOnce($run = true)
    {
        $this->attemptOnce = (bool)$run;
        return $this;
    }

    /**
     * Set job to only attempt to run once
     *
     * @return boolean
     */
    public function isAttemptOnce()
    {
        return $this->attemptOnce;
    }

    /**
     * Set job as running
     *
     * @return JobInterface
     */
    public function setAsRunning()
    {
        $this->running = true;
        return $this;
    }

    /**
     * Is job running
     *
     * @return boolean
     */
    public function isRunning()
    {
        return $this->running;
    }

    /**
     * Set job as completed
     *
     * @return JobInterface
     */
    public function setAsCompleted()
    {
        $this->completed = true;
        return $this;
    }

    /**
     * Is job complete
     *
     * @return boolean
     */
    public function isComplete()
    {
        return $this->completed;
    }

    /**
     * Set job as failed
     *
     * @return JobInterface
     */
    public function setAsFailed()
    {
        $this->failed = true;
        return $this;
    }

    /**
     * Has job failed
     *
     * @return boolean
     */
    public function hasFailed()
    {
        return $this->failed;
    }

    /**
     * Run job
     *
     * @return mixed
     */
    public function run()
    {
        if ($this->hasCallable()) {
            return $this->loadCallable();
        }
        if ($this->hasCommand() && ($this->hasProcessor()) && ($this->getProcessor()->hasQueue()) &&
            ($this->getProcessor()->getQueue()->hasApplication())) {
            return $this->runCommand($this->getProcessor()->getQueue()->application());
        }
        if ($this->hasExec()) {
            return $this->runExec();
        }

        return null;
    }

    /**
     * Load callable
     *
     * @throws \ReflectionException
     * @throws Exception
     * @return mixed
     */
    protected function loadCallable()
    {
        if (null === $this->callable) {
            throw new Exception('Error: The callable for this job was not set.');
        }

        $callable = $this->callable;
        $params   = $this->params;
        $result   = null;

        if ((null !== $params) && !is_array($params)) {
            $params = [$params];
        }

        // If the callable is a closure
        if ($callable instanceof \Closure) {
            $result = (!empty($params)) ? call_user_func_array($callable, $params) : $callable();
        // If the callable is a string
        } else if (is_string($callable)) {
            if (strpos($callable, '->') !== false) {
                list($class, $method) = explode('->', $callable);
                if (class_exists($class) && method_exists($class, $method)) {
                    $result = (!empty($params)) ?
                        call_user_func_array([new $class(), $method], $params) : call_user_func([new $class(), $method]);
                }
            } else if (strpos($callable, '::') !== false) {
                $result = (!empty($params)) ?
                    call_user_func_array($callable, $params) : call_user_func($callable);
            } else if (class_exists($callable)) {
                $result = (!empty($params)) ?
                    (new \ReflectionClass($callable))->newInstanceArgs($params) : new $callable();
            }
        }

        return $result;
    }

    /**
     * Run application command
     *
     * @param Application $app
     * @return mixed
     */
    protected function runCommand(Application $app)
    {
        if (array_key_exists($this->command, $app->router()->getRouteMatch()->getRoutes())) {
            $output = null;

            ob_start();
            $app->run(true, $this->command);
            $output = ob_get_clean();

            return array_filter(explode(PHP_EOL, $output));
        }

        return false;
    }

    /**
     * Run CLI executable command
     *
     * @return mixed
     */
    protected function runExec()
    {
        $output = [];
        exec($this->exec, $output);
        return $output;
    }

    /**
     * Sleep magic method
     *
     * @return array
     */
    public function __sleep()
    {
        if (!empty($this->callable) && ($this->callable instanceof \Closure)) {
            $this->serializedCallable = (new Serializer())->serialize($this->callable);
            $this->callable = null;
        }

        return array_keys(get_object_vars($this));
    }

    /**
     * Wakeup magic method
     *
     * @return void
     */
    public function __wakeup()
    {
        if (!empty($this->serializedCallable)) {
            $this->callable = (new Serializer())->unserialize($this->serializedCallable);
            $this->serializedCallable = null;
        }
    }

}