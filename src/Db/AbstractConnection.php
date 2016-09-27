<?php
namespace Friday\Db;

use Friday\Base\BaseObject;
use Friday\Base\ContextInterface;
use Friday\Base\EventTrait;

abstract class AbstractConnection extends BaseObject implements ContextInterface{
    /**
     * @var Adapter
     */
    public $adapter;

    use EventTrait;

}