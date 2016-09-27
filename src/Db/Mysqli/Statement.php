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
     * @var int|null
     */
    private $_rowCount;
    /**
     * @var int|null
     */
    private $_insertId;
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
        return $this->getResult()->fetch_array($this->driverFetchMode($fetchMode));
    }

    /**
     * @inheritdoc
     */
    public function fetchAll($fetchMode = null)
    {

        return $this->getResult()->fetch_all($this->driverFetchMode($fetchMode));
    }

    /**
     * @param null $fetchMode
     * @return int
     */
    protected function driverFetchMode($fetchMode = null) : int {
        if($fetchMode === null){
            $fetchMode = $this->_fetchMode;
        }

        switch ($fetchMode) {
            case AbstractStatement::FETCH_ASSOC:
                return MYSQLI_ASSOC;
            case AbstractStatement::FETCH_NUM:
                return MYSQLI_NUM;
            case AbstractStatement::FETCH_BOTH:
                return MYSQLI_BOTH;
        }

        throw new InvalidArgumentException("Fetch mode has bad value.");
    }
    /**
     * @inheritdoc
     */
    public function fetchColumn($columnNumber = 0)
    {
        if($columnNumber < 0 && $columnNumber >= $this->getResult()->field_count) {
            throw new InvalidArgumentException('$columnNumber out of rage [0..'.$this->getResult()->field_count.']');
        }
        $rows = $this->getResult()->fetch_all(MYSQLI_NUM);
        $result = [];

        foreach ($rows as $row) {

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
        if($this->_rowCount === null) {
            if(null === $result = $this->getResult()){
                return 0;
            }

            return $result->num_rows;
        } else {
            return $this->_rowCount;
        }

    }

    /**
     * @return int
     */
    public function columnCount() : int {
        return $this->getResult()->field_count;
    }

    public function free()
    {
        return $this->getResult()->free_result();
    }

    /**
     * @param $rowCount
     * @internal
     */
    public function setRowCount($rowCount){
        $this->_rowCount = $rowCount;
    }

    /**
     * @param $insertId
     * @internal
     */
    public function setInsertId($insertId){
        $this->_insertId = $insertId;
    }

    public function insertId()
    {
        return $this->_insertId;
    }
}