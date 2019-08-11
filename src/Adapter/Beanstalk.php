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

use Pheanstalk\Connection;
use Pheanstalk\Pheanstalk;

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
class Beanstalk extends AbstractAdapter
{

    /**
     * Pheanstalk object
     * @var Pheanstalk
     */
    protected $pheanstalk = null;

    /**
     * Constructor
     *
     * Instantiate the beanstalk queue object
     *
     * @param  string $host
     * @param  int    $port
     * @param  int    $timeout
     */
    public function __construct($host = 'localhost', $port = null, $timeout = null)
    {
        $port    = $port ?? Pheanstalk::DEFAULT_PORT;
        $timeout = $timeout ?? Connection::DEFAULT_CONNECT_TIMEOUT;

        $this->pheanstalk = Pheanstalk::create($host, $port, $timeout);
    }

    /**
     * Get the pheanstalk object.
     *
     * @return Pheanstalk
     */
    public function pheanstalk()
    {
        return $this->pheanstalk;
    }

}
