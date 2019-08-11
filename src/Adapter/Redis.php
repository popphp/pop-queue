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
namespace Pop\Queue\Adapter;

use Pop\Queue\Processor\Jobs\AbstractJob;

/**
 * Redis queue adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Redis extends AbstractAdapter
{

    /**
     * Redis object
     * @var \Redis
     */
    protected $redis = null;

    /**
     * Constructor
     *
     * Instantiate the redis queue object
     *
     * @param  string $host
     * @param  int    $port
     * @throws Exception
     */
    public function __construct($host = 'localhost', $port = 6379)
    {
        if (!class_exists('Redis', false)) {
            throw new Exception('Error: Redis is not available.');
        }

        $this->redis = new \Redis();
        if (!$this->redis->connect($host, (int)$port)) {
            throw new Exception('Error: Unable to connect to the redis server.');
        }
    }

    /**
     * Push job onto queue stack
     *
     * @param  AbstractJob $job
     * @return void
     */
    public function push(AbstractJob $job)
    {

    }

    /**
     * Pop job off of queue stack
     *
     * @return void
     */
    public function pop()
    {

    }

    /**
     * Get the redis object.
     *
     * @return \Redis
     */
    public function redis()
    {
        return $this->redis;
    }

}
