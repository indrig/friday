<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Base\Deferred;
use Friday\Db\Exception\Exception;
use Friday\Db\ResultInterface;
use Friday\Db\StatementInterface;

class Statement extends BaseObject implements StatementInterface {

    /**
     * @var \mysqli
     */
    private $mysqli;

    /**
     * @var \mysqli_stmt
     */
    private $resource;

    /**
     * Is prepared
     *
     * @var bool
     */
    protected $isPrepared = false;

    /**
     * @var string
     */
    protected $_sql = '';

    /**
     * Initialize
     *
     * @param  \mysqli $mysqli
     * @return Statement
     */
    public function initialize(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
        return $this;
    }



    /**
     * Is prepared
     *
     * @return bool
     */
    public function isPrepared()
    {
        return $this->isPrepared;
    }
    /**
     * Prepare
     *
     * @param string $sql
     * @throws Exception
     * @return Statement
     */
    public function prepare($sql = null) : Awaitable
    {
        if ($this->isPrepared) {
            throw new Exception('This statement has already been prepared');
        }
        $sql = ($sql) ?: $this->_sql;
        $this->resource = $this->mysqli->prepare($sql);
        if (!$this->resource instanceof \mysqli_stmt) {
            throw new Exception(
                'Statement couldn\'t be produced with sql: ' . $sql . ', '.$this->mysqli->error
            );
        }

        $this->isPrepared = true;
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
        $deferred = new Deferred();


        if(false === $status = $this->mysqli->query($this->getSql(), MYSQLI_ASYNC)){
            $deferred->exception(new Exception($this->mysqli->error));
        } else {

           Friday::$app->getLooper()->taskPeriodic(function (){

               $this->mysqli->poll();
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
        if($this->resource !== null) {
            return $this->resource->affected_rows;
        }

        return 0;
    }
    public function bindParam($name, $value, $dataType, $length = null){
        //();
    }

}