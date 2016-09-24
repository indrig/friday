<?php
namespace Friday\Db\Mysqli;

use Friday\Base\BaseObject;
use Friday\Db\AbstractConnection;
use Friday\Db\Adapter;
use Friday\Db\Exception\Exception;
use mysqli;

class Connection extends AbstractConnection  {


    /**
     * @var mysqli
     */
    private $resource;

    /**
     * @return $this
     * @throws Exception
     */
    public function connect(){
        if($this->isConnected()) {
            return $this;
        }
        $adapter = $this->adapter;

        $resource = $this->resource = new mysqli();
        $resource->init();

        $resource->real_connect($adapter->host, $adapter->username, $adapter->password, $adapter->database);

        if($resource->connect_error){
            throw new Exception($resource->connect_error, $resource->connect_errno);
        }

        if (!empty($adapter->charset)) {
            $this->resource->set_charset(empty($adapter->charset));
        }

        return $this;
    }

    /**
     *
     */
    public function disconnect(){
        if ($this->resource instanceof \mysqli) {
            $this->resource->close();
        }
        $this->resource = null;
    }

    /**
     * @return bool
     */
    public function isConnected() : bool
    {
        return ($this->resource instanceof \mysqli);
    }


    public function prepare($sql){
        $this->connect();

        $statement = new Statement();
        $statement->initialize($this->resource);
        $statement->setSql($sql);
        $statement->prepare();

        return $statement;
    }

    public function free() {
        $remove = false;
        if ($this->resource->errno == 2006) {
            $remove = true;
        }

        $this->adapter->getConnectionPool()->free($this, $remove);
    }
}