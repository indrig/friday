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

    abstract public function connect();
    abstract public function disconnect();
    abstract public function isConnected() : bool ;

    abstract public function free();
    abstract public function prepare($sql);
}