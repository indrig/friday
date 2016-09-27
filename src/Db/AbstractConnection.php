<?php
namespace Friday\Db;

use Friday\Base\BaseObject;
use Friday\Base\EventTrait;

abstract class AbstractConnection extends BaseObject implements ConnectionInterface{
    /**
     * @var Adapter
     */
    protected $_adapter;

    use EventTrait;

    /**
     * @inheritdoc
     */
    public function setAdapter($adapter)
    {
        $this->_adapter = $adapter;
    }

    /**
     * @inheritdoc
     */
    public function getAdapter()
    {
        return $this->_adapter;
    }

}