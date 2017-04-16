<?php
namespace Friday\Db\Mysqli;

use Friday\Db\Transaction;
use Friday;
use Friday\Base\Exception\InvalidArgumentException;
use Friday\Db\AbstractConnection;
use Friday\Db\Exception\ConnectionException;
use Friday\Db\Exception\Exception;
use mysqli;

class Connection extends AbstractConnection  {

    /**
     * @var Transaction
     */
    private $_transaction;
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
        $adapter = $this->getAdapter();

        $resource = $this->_resource = new mysqli();
        $resource->init();

        if(false === @$resource->real_connect($adapter->host, $adapter->username, $adapter->password, $adapter->database)){
            throw new ConnectionException($resource->connect_error, (int)$resource->connect_errno);

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
            'class'         => 'Friday\Db\Mysqli\Statement',
            'connection'    => $this,
            'sql'           => $sql,
        ]);

        return $statement;
    }

    public function free() {
        $remove = false;
        if ($this->_resource->errno == 2006) {
            $remove = true;
        }

        /**
        $this->_resource->begin_transaction();
        $this->_resource->rollback();
        $this->_resource->commit();
*/
        $this->getAdapter()->getConnectionPool()->free($this, $remove);
    }


}