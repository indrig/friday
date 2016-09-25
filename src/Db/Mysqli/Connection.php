<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Base\BaseObject;
use Friday\Base\Exception\InvalidArgumentException;
use Friday\Base\Task;
use Friday\Db\AbstractConnection;
use Friday\Db\Adapter;
use Friday\Db\Exception\Exception;
use mysqli;

class Connection extends AbstractConnection  {
    /**
     * @var mysqli
     */
    private $_resource;

    /**
     * @return mysqli
     */
    public function getResource(){
        return $this->_resource;
    }

    /**
     * @param $resource
     * @return $this
     */
    public function setResource($resource){
        if(!$resource instanceof mysqli) {
            throw  new InvalidArgumentException('$resource is not mysqli.');
        }
        $this->_resource = $resource;
        return $this;
    }
    /**
     * @return $this
     * @throws Exception
     */
    public function connect(){
        if($this->isConnected()) {
            return $this;
        }
        $adapter = $this->adapter;

        $resource = $this->_resource = new mysqli();
        $resource->init();

        $resource->real_connect($adapter->host, $adapter->username, $adapter->password, $adapter->database);

        if($resource->connect_error){
            throw new Exception($resource->connect_error, $resource->connect_errno);
        }

        if (!empty($adapter->charset)) {
            $this->_resource->set_charset(empty($adapter->charset));
        }

        return $this;
    }

    /**
     *
     */
    public function disconnect(){
        if ($this->_resource instanceof \mysqli) {
            $this->_resource->close();
        }
        $this->_resource = null;
    }

    /**
     * @return bool
     */
    public function isConnected() : bool
    {
        return ($this->_resource instanceof mysqli);
    }


    /**
     * @inheritdoc
     */
    public function prepare($sql){
        $this->connect();

        $statement = Friday::createObject([
            'class' => 'Friday\Db\Mysqli\Statement',
            'connection' => $this,
            'sql' => $sql,
        ]);
        $statement->prepare();

        return $statement;
    }

    public function free() {
        $remove = false;
        if ($this->_resource->errno == 2006) {
            $remove = true;
        }

        $this->adapter->getConnectionPool()->free($this, $remove);
    }

}