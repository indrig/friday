<?php
namespace Friday\Db;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Db\Exception\Exception;

abstract class AbstractStatement extends BaseObject
{
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
}