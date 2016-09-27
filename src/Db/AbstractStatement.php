<?php
namespace Friday\Db;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;

abstract class AbstractStatement extends BaseObject implements StatementInterface
{

    /**
     * @var AbstractConnection
     */
    protected $_connection;

    /**
     * @var string
     */
    protected $_sql = '';

    /**
     * @var string
     */
    protected $_prepared = false;

    /**
     * @var ParameterContainer|null
     */
    protected $_parameterContainer;

    /**
     * @var \mysqli_result|resource
     */
    protected $_result;

    protected $_fetchMode;

    public function setFetchMode($fetchMode){
        $this->_fetchMode = $fetchMode;
    }
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
     *
     */
    protected function buildQueryWithParameters(){
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
            } else {
                $sql = '';
                foreach (explode('?', $this->_sql) as $i => $part) {
                    $sql .= (isset($params[$i]) ? $params[$i] : '') . $part;
                }

                return $sql;
            }
        } else {
            return $this->_sql;
        }
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
     * @return $this
     */
    public function bindValue($name, &$value, $dataType) {
        $this->getParameterContainer()->bindValue($name, $value, $dataType);
        return $this;
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
     * @param \mysqli_result $result
     * @return $this
     */
    public function setResult($result)
    {
        $this->_result = $result;
        return $this;
    }

    /**
     * @return \mysqli_result
     */
    public function getResult()
    {
        return $this->_result;
    }

}