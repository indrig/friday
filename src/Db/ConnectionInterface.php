<?php
namespace Friday\Db;

interface ConnectionInterface{
    /**
     * @return mixed
     */
    public function connect();

    /**
     * @return mixed
     */
    public function disconnect();

    /**
     * @return bool
     */
    public function isConnected() : bool ;

    /**
     * Free connection on pool
     * @return void
     */
    public function free();

    /**
     * @param $sql
     * @return AbstractStatement
     */
    public function prepare($sql);

    /**
     * @param Adapter $adapter
     */
    public function setAdapter($adapter);

    /**
     * @return Adapter
     *
     */
    public function getAdapter();
}