<?php
namespace Friday\Db;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Db\Exception\Exception;

abstract class AbstractStatement extends BaseObject
{
    const FETCH_ASSOC = 2;
    const FETCH_NUM = 3;
    const FETCH_BOTH = 4;

    /**
     * @var AbstractConnection
     */
    private $_connection;

    /**
     * @var string
     */
    protected $_sql = '';

    /**
     * @var string
     */
    protected $_preparedSql;

    /**
     * @var ParameterContainer|null
     */
    protected $_parameterContainer;

    /**
     * @var AbstractResult
     */
    private $_result;

    /**
     * Execute
     *
     * @param null|array $parameters
     * @return Awaitable
     */
    abstract public function execute($parameters = null) : Awaitable;

    /**
     * Initialize
     *
     * @param  AbstractConnection $connection
     * @return $this
     */
    public function setConnection(AbstractConnection $connection)
    {
        $this->_connection = $connection;
        return $this;
    }
    /**
     * Initialize
     * @return AbstractConnection
     */
    public function getConnection()
    {
        return $this->_connection;
    }

    /**
     * Prepare
     *
     * @param string $sql
     * @throws Exception
     * @return $this
     */
    public function prepare($sql = null)
    {
        if ($this->_preparedSql !== null) {
            throw new Exception('This statement has already been prepared');
        }
        $sql = ($sql) ?: $this->_sql;

        $parameterContainer = $this->getParameterContainer();
        if($parameterContainer->count() > 0){
            $params = [];

            $parameters = $parameterContainer->getNamedArray();
            $adapter = $this->getConnection()->adapter;

            foreach ($parameters as $name => &$value) {
                if (is_string($name) && strncmp(':', $name, 1)) {
                    $name = ':' . $name;
                }
                if ($parameterContainer->offsetHasType($name)) {
                    switch ($parameterContainer->offsetHasType($name)) {
                        case ParameterContainer::TYPE_DOUBLE:
                            $params[$name] = floatval($value);
                            break;
                        case ParameterContainer::TYPE_NULL:
                            $params[$name] = 'NULL';
                            break;
                        case ParameterContainer::TYPE_INTEGER:
                            $params[$name] = intval($value);
                            break;
                        case ParameterContainer::TYPE_STRING:
                        default:
                            $params[$name] = $adapter->quoteValue($value);
                            break;
                    }
                } else {
                    $params[$name] = $adapter->quoteValue($value);
                }
            }
            if (!isset($params[1])) {
                return strtr($this->_sql, $params);
            }
            $sql = '';
            foreach (explode('?', $this->_sql) as $i => $part) {
                $sql .= (isset($params[$i]) ? $params[$i] : '') . $part;
            }

            $this->_preparedSql = $sql;
        } else {
            $this->_preparedSql = $sql;
        }

        return $this;
    }

    /**
     * Is prepared
     *
     * @return bool
     */
    public function isPrepared()
    {
        return $this->_preparedSql !== null;
    }


    /**
     * @return ParameterContainer|null
     */
    protected function getParameterContainer(){
        if($this->_parameterContainer === null) {
            $this->_parameterContainer = new ParameterContainer();
        }
        return $this->_parameterContainer;
    }


    /**
     * @param $name
     * @param $value
     * @param $dataType
     * @param null $length
     * @return $this
     */
    public function bindParam($name, $value, $dataType, $length = null) {
        $this->getParameterContainer()->bindParam($name, $value, $dataType, $length);

        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @param $dataType
     */
    public function bindValue($name, &$value, $dataType) {
        $this->getParameterContainer()->bindValue($name, $value, $dataType);
    }


    /**
     * Set sql
     *
     * @param  string $sql
     * @return $this
     */
    public function setSql($sql)
    {
        $this->_sql = $sql;
        return $this;
    }

    /**
     * Get sql
     *
     * @return string
     */
    public function getSql()
    {
        return $this->_sql;
    }

    /**
     * Set sql
     *
     * @param AbstractResult
     * @return $this
     */
    public function setResult($result)
    {
        $this->_result = $result;
        return $this;
    }

    /**
     * @return AbstractResult
     */
    public function getResult()
    {
        return $this->_result;
    }

    /**
     * Извлечение следующей строки из результирующего набора
     *
     * @param null|integer $fetchMode
     * @return false|array
     */
    abstract public function fetch($fetchMode = null);

    /**
     * Возвращает массив, содержащий все строки результирующего набора
     *
     * @param null|integer $fetchMode
     * @return array
     */
    abstract public function fetchAll($fetchMode = null);

    /**
     * Возвращает данные одного столбца следующей строки результирующего набора
     *
     * @param int $columnNumber
     * @return mixed|false
     */
    abstract public function fetchColumn($columnNumber = 0);

    /**
     * @param string $className
     * @param array $classArguments
     * @return object
     */
    abstract public function fetchObject(string $className, array $classArguments = []);
}