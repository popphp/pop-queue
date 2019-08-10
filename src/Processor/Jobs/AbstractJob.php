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
     * Job ID
     * @var string
     */
    protected $id = null;

    /**
     * The processor the job belongs to (Worker or Schedule)
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
     * Constructor
     *
     * Instantiate the job object
     *
     * @param  mixed             $callable
     * @param  mixed             $params
     * @param  AbstractProcessor $processor
     * @param  string            $id
     */
    public function __construct($callable, $params = null, AbstractProcessor $processor = null, $id = null)
    {
        $this->callable = $callable;
        $this->params   = $params;

        if (null !== $processor) {
            $this->setProcessor($processor);
        }
        if (null !== $id) {
            $this->setJobId($id);
        }
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
        return $this->loadCallable();
    }

    /**
     * Load callable
     *
     * @throws \ReflectionException
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

}