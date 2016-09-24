<?php
namespace Friday\Db;
use Friday\Base\Awaitable;

interface StatementInterface
{
    /**
     * Prepare sql
     *
     * @param string $sql
     */
    public function prepare($sql = null);

    /**
     * Check if is prepared
     *
     * @return bool
     */
    public function isPrepared();

    /**
     * Execute
     *
     * @param null|array $parameters
     * @return Awaitable
     */
    public function execute($parameters = null) : Awaitable;

    public function rowCount() : int ;

    public function bindParam($name, $value, $dataType, $length);
}