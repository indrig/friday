<?php
namespace Friday\Db\Exception;

class TableNotExistsException extends Exception{
    /**
     * Constructor.
     * @param string $tableName

     * @param integer $code PDO error code
     * @param \Exception $previous The previous exception used for the exception chaining.
     */
    public function __construct($tableName, $code = 0, \Exception $previous = null)
    {
        parent::__construct("Table {$tableName} not exists.", $code, $previous);
    }

}