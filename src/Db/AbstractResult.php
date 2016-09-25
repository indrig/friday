<?php
namespace Friday\Db;

abstract class AbstractResult{
    /**
     * @var AbstractStatement
     */
    protected $_statement;

    /**
     * @param $statement
     * @return $this
     */
    public function setStatement(AbstractStatement $statement){
        $this->_statement = $statement;
        return $this;
    }

    /**
     * @return AbstractStatement
     */
    public function getStatement(){
        return $this->_statement;
    }

    /**
     * @var \mysqli_result|resource
     */
    protected $_resource;

    /**
     * @param \mysqli_result|resource $resource
     * @return $this
     */
    public function setResource($resource){
        $this->_resource = $resource;
        return $this;
    }

    /**
     * @return \mysqli_result|resource
     */
    public function getResource(){
        return $this->_resource;
    }
}