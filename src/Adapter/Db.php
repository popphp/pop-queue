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

use Pop\Db\Adapter\AbstractAdapter as DbAdapter;

/**
 * Database queue adapter class
 *
 * @category   Pop
 * @package    Pop\Queue
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2019 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    0.0.1a
 */
class Db extends AbstractAdapter
{

    /**
     * Database adapter
     * @var DbAdapter
     */
    protected $db = null;

    /**
     * Constructor
     *
     * Instantiate the database queue object
     *
     * @param  DbAdapter $db
     */
    public function __construct(DbAdapter $db)
    {
        $this->db = $db;
    }

    /**
     * Get the redis object.
     *
     * @return DbAdapter
     */
    public function db()
    {
        return $this->db;
    }

}
