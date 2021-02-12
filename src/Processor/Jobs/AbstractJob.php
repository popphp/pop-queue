<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Queue\Processor\Jobs;

use Pop\Application;
use Pop\Utils\CallableObject;
use Opis\Closure\SerializableClosure;

/**
 * Abstract job class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2021 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    1.2.0
 */
abstract class AbstractJob implements JobInterface
{

    /**
     * Job ID
     * @var string
     */
    protected $id = null;

    /**
     * Job Description
     * @var string
     */
    protected $description = null;

    /**
     * Job callable
     * @var CallableObject
     */
    protected $callable = null;

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
     * Serialize closure
     * @var \Closure
     */
    protected $serializedClosure = null;

    /**
     * Serialize parameters
     * @var array
     */
    protected $serializedParameters = null;

    /**
     * Constructor
     *
     * Instantiate the job object
     *
     * @param  mixed  $callable
     * @param  mixed  $params
     * @param  string $id
     * @param  string $description
     */
    public function __construct($callable = null, $params = null, $id = null, $description = null)
    {
        if (null !== $callable) {
            $this->setCallable($callable, $params);
        }
        if (null !== $id) {
            $this->setJobId($id);
        }
        if (null !== $description) {
            $this->setJobDescription($description);
        }
    }

    /**
     * Generate job ID
     *
     * @return string
     */
    public function generateJobId()
    {
        $this->id = sha1(uniqid(rand()) . time());
        return $this->id;
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
     * Set job description
     *
     * @param  string $description
     * @return AbstractJob
     */
    public function setJobDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get job description
     *
     * @return string
     */
    public function getJobDescription()
    {
        return $this->description;
    }

    /**
     * Has job description
     *
     * @return boolean
     */
    public function hasJobDescription()
    {
        return (null !== $this->description);
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

        if (!($callable instanceof CallableObject)) {
            $this->callable = new CallableObject($callable, $params);
        } else {
            $this->callable = $callable;
            if (null !== $params) {
                if (is_array($params)) {
                    $this->callable->addParameters($params);
                } else {
                    $this->callable->addParameter($params);
                }
            }
        }

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
     * @return CallableObject
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
     * Set job to only attempt once
     *
     * @param  boolean $attemptOnce
     * @return JobInterface
     */
    public function attemptOnce($attemptOnce = true)
    {
        $this->attemptOnce = (bool)$attemptOnce;
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
     * @param  Application $application
     * @return mixed
     */
    public function run(Application $application = null)
    {
        if ($this->hasCallable()) {
            return $this->loadCallable($application);
        }
        if (($this->hasCommand()) && (null !== $application)) {
            return $this->runCommand($application);
        }
        if ($this->hasExec()) {
            return $this->runExec();
        }

        return null;
    }

    /**
     * Load callable
     *
     * @param  Application $application
     * @throws Exception
     * @return mixed
     */
    protected function loadCallable(Application $application = null)
    {
        if (null === $this->callable) {
            throw new Exception('Error: The callable for this job was not set.');
        }

        if (null !== $application) {
            if ($this->callable->hasParameters()) {
                $parameters = $this->callable->getParameters();
                array_unshift($parameters, $application);
                $this->callable->setParameters($parameters);
            } else {
                $this->callable->addNamedParameter('application', $application);
            }
        }

        return $this->callable->call();
    }

    /**
     * Run application command
     *
     * @param Application $application
     * @return mixed
     */
    protected function runCommand(Application $application)
    {
        if (array_key_exists($this->command, $application->router()->getRouteMatch()->getRoutes())) {
            $output = null;

            ob_start();
            $application->run(true, $this->command);
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
        if (!empty($this->callable) && ($this->callable->getCallable() instanceof \Closure)) {
            $serializedClosure       = new SerializableClosure($this->callable->getCallable());
            $this->serializedClosure = serialize($serializedClosure);
            if ($this->callable->hasParameters()) {
                $this->serializedParameters = $this->callable->getParameters();
            }
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
        if (!empty($this->serializedClosure)) {
            $serializedClosure          = unserialize($this->serializedClosure);
            $callable                   = $serializedClosure->getClosure();
            $this->callable             = new CallableObject($callable, $this->serializedParameters);
            $this->serializedClosure    = null;
            $this->serializedParameters = null;
        }
    }

}