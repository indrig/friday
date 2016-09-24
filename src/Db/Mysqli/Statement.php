<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Base\Deferred;
use Friday\Db\Exception\Exception;
use Friday\Db\ParameterContainer;
use Friday\Db\StatementInterface;

class Statement extends BaseObject implements StatementInterface {

    /**
     * @var Connection
     */
    private $connection;

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
     * Initialize
     *
     * @param  Connection $connection
     * @return Statement
     */
    public function initialize(Connection $connection)
    {
        $this->connection = $connection;
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
            $adapter = $this->connection->adapter;

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
     * Execute
     *
     * @param null|array $parameters
     * @return Awaitable
     */
    public function execute($parameters = null) : Awaitable
    {
        $id       = spl_object_hash($this);
        $deferred = new Deferred();
        $resource = $this->connection->getResource();

        if(false === $status = $this->connection->getResource()->query($resource, MYSQLI_ASYNC)){
            $deferred->exception(new Exception($resource->error));
        } else {

           Friday::$app->getLooper()->taskPeriodic(function () use ($resource){
               $links[] = $errors[] = $reject[] = $resource;
               mysqli_poll($links, $errors, $reject, 0);

               $each = array('links' => $links, 'errors' => $errors, 'reject' => $reject) ;

              // $links = $errors = $reject = $this->mysqli;
              // mysqli_poll($links, $errors, $reject, 0); // don't wait, just check
           }, 0.01);
        }

        return $deferred->awaitable();
    }


    /**
     * Set sql
     *
     * @param  string $sql
     * @return Statement
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

    public function rowCount() : int
    {
       // if($this->resource !== null) {
       //     return $this->resource->affected_rows;
      //  }

        return 0;
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
     * @return ParameterContainer|null
     */
    protected function getParameterContainer(){
        if($this->_parameterContainer === null) {
            $this->_parameterContainer = new ParameterContainer();
        }
        return $this->_parameterContainer;
    }
}