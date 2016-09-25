<?php
namespace Friday\Db;

use Friday\Base\BaseObject;
use Friday\Base\EventTrait;

abstract class AbstractConnection extends BaseObject {
    /**
     * @var Adapter
     */
    public $adapter;

    use EventTrait;

    /**
     * @return mixed
     */
    abstract public function connect();

    /**
     * @return mixed
     */
    abstract public function disconnect();

    /**
     * @return bool
     */
    abstract public function isConnected() : bool ;

    /**
     * Free connection on pool
     * @return void
     */
    abstract public function free();

    /**
     * @param $sql
     * @return AbstractStatement
     */
    abstract public function prepare($sql);
}