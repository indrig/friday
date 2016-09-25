<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Base\Deferred;
use Friday\Db\Exception\Exception;
use Friday\Db\ParameterContainer;
use Friday\Db\AbstractStatement;

class Statement extends AbstractStatement {
    /**
     * Execute
     *
     * @param null|array $parameters
     * @return Awaitable
     */
    public function execute($parameters = null) : Awaitable
    {
        $deferred   = new Deferred();
        /**
         * @var Connection $connection
         */
        $connection = $this->getConnection();
        $resource   = $connection->getResource();

        if(false === $status = $resource->query($this->_preparedSql, MYSQLI_ASYNC)){

            $deferred->exception(new Exception($resource->error));
            $connection->free();
        } else {
            Client::addQueryPoolAwait($this, $deferred);
        }
        return $deferred->awaitable();
    }

    /**
     *  @inheritdoc
     */
    public function fetch($fetchMode = null)
    {
        $driverFetchMode = null;
        switch ($fetchMode) {
            case AbstractStatement::FETCH_ASSOC:
                $driverFetchMode = MYSQLI_ASSOC;
                break;
            case AbstractStatement::FETCH_NUM:
                $driverFetchMode = MYSQLI_NUM;
                break;
            case AbstractStatement::FETCH_BOTH:
                $driverFetchMode = MYSQLI_BOTH;
                break;
        }
        return $this->getResult()->getResource()->fetch_array($driverFetchMode);
    }

    /**
     * @inheritdoc
     */
    public function fetchAll($fetchMode = null)
    {
        $driverFetchMode = null;
        switch ($fetchMode) {
            case AbstractStatement::FETCH_ASSOC:
                $driverFetchMode = MYSQLI_ASSOC;
                break;
            case AbstractStatement::FETCH_NUM:
                $driverFetchMode = MYSQLI_NUM;
                break;
            case AbstractStatement::FETCH_BOTH:
                $driverFetchMode = MYSQLI_BOTH;
                break;
        }
        return $this->getResult()->getResource()->fetch_all($driverFetchMode);
    }

    /**
     * @inheritdoc
     */
    public function fetchColumn($columnNumber = 0)
    {
        if(false === $row = $this->getResult()->getResource()->fetch_array(MYSQLI_NUM)){
            return false;
        }
        return $row[$columnNumber];
    }

    /**
     * @inheritdoc
     */
    public function fetchObject(string $className, array $classArguments = [])
    {
        return $this->getResult()->getResource()->fetch_object($className, $classArguments);
    }
}