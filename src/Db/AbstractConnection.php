<?php
namespace Friday\Db;

use Friday\Base\BaseObject;

abstract class AbstractConnection extends BaseObject {
    abstract public function open();
    abstract public function close();
}