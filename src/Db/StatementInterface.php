<?php
namespace Friday\Db;

use Friday\Base\Awaitable;

interface StatementInterface{
    const FETCH_ASSOC = 2;
    const FETCH_NUM = 3;
    const FETCH_BOTH = 4;

    public function setFetchMode($fetchMode);
    /**
     * Execute
     * @return Awaitable
     */
    public function execute() : Awaitable;

    /**
     * Initialize
     *
     * @param  AbstractConnection $connection
     * @return $this
     */
    public function setConnection(AbstractConnection $connection);

    /**
     * Initialize
     * @return AbstractConnection
     */
    public function getConnection();


    /**
     * @param $name
     * @param $value
     * @param $dataType
     * @param null $length
     * @return $this
     */
    public function bindParam($name, $value, $dataType, $length = null) ;

    /**
     * @param $name
     * @param $value
     * @param $dataType
     * @return $this
     */
    public function bindValue($name, &$value, $dataType);


    /**
     * Set sql
     *
     * @param  string $sql
     * @return $this
     */
    public function setSql($sql);

    /**
     * Get sql
     *
     * @return string
     */
    public function getSql();

    /**
     * Set result
     *
     * @param \mysqli_result|resource $result
     * @return $this
     */
    public function setResult($result);

    /**
     * @return \mysqli_result|resource
     */
    public function getResult();

    /**
     * Извлечение следующей строки из результирующего набора
     *
     * @param null|integer $fetchMode
     * @return false|array
     */
    public function fetch($fetchMode = null);

    /**
     * Возвращает массив, содержащий все строки результирующего набора
     *
     * @param null|integer $fetchMode
     * @return array
     */
    public function fetchAll($fetchMode = null);

    /**
     * Возвращает данные одного столбца следующей строки результирующего набора
     *
     * @param int $columnNumber
     * @return mixed|false
     */
    public function fetchColumn($columnNumber = 0);

    /**
     *
     * @param int
     * @return mixed|false
     */
    public function fetchScalar($columnNumber = 0);

    /**
     * @param string $className
     * @param array $classArguments
     * @return object
     */
    public function fetchObject(string $className, array $classArguments = []);

    public function rowCount() : int ;

    public function columnCount() : int ;

    public function free();


}