<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Base\Deferred;
use Friday\Base\Exception\InvalidArgumentException;
use Friday\Db\Exception\Exception;
use Friday\Db\ParameterContainer;
use Friday\Db\AbstractStatement;

class Statement extends AbstractStatement {
    /**
     * Execute
     *
     * @return Awaitable
     */
    public function execute() : Awaitable
    {
        $deferred   = new Deferred();
        /**
         * @var Connection $connection
         */
        $connection = $this->getConnection();
        $resource   = $connection->getResource();

        $preparedSql = $this->buildQueryWithParameters();
        if(false === $status = $resource->query($preparedSql, MYSQLI_ASYNC)){

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
        return $this->getResult()->fetch_array($driverFetchMode);
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
        return $this->getResult()->fetch_all($driverFetchMode);
    }

    /**
     * @inheritdoc
     */
    public function fetchColumn($columnNumber = 0)
    {
        var_dump('fetchColumn');
        if($columnNumber < 0 && $columnNumber >= $this->getResult()->field_count) {
            throw new InvalidArgumentException('$columnNumber out of rage [0..'.$this->getResult()->field_count.']');
        }
        $rows = $this->getResult()->fetch_all(MYSQLI_NUM);
        $result = [];

        foreach ($rows as $row) {        var_dump($row, $columnNumber);

            $result[] = $row[$columnNumber];
        }
        return $result;
    }


    /**
     * @inheritdoc
     */
    public function fetchScalar($columnNumber = 0)
    {
        if(false === $row = $this->getResult()->fetch_array(MYSQLI_NUM)){
            return false;
        }
        return $row[$columnNumber];
    }

    /**
     * @inheritdoc
     */
    public function fetchObject(string $className, array $classArguments = [])
    {
        return $this->getResult()->fetch_object($className, $classArguments);
    }

    /**
     * @return int
     */
    public function rowCount() : int {
        return $this->getResult()->num_rows;
    }
}