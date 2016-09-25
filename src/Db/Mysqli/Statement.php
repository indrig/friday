<?php
namespace Friday\Db\Mysqli;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\BaseObject;
use Friday\Base\Deferred;
use Friday\Db\Exception\Exception;
use Friday\Db\ParameterContainer;
use Friday\Db\AbstractStatement;

class Statement extends AbstractStatement {
    /**
     * Execute
     *
     * @param null|array $parameters
     * @return Awaitable
     */
    public function execute($parameters = null) : Awaitable
    {
        $deferred   = new Deferred();
        /**
         * @var Connection $connection
         */
        $connection = $this->getConnection();
        $resource   = $connection->getResource();

        if(false === $status = $resource->query($resource, MYSQLI_ASYNC)){
            $deferred->exception(new Exception($resource->error));
            $connection->free();
        } else {
            Client::addQueryPoolAwait($this, $deferred);
        }
        return $deferred->awaitable();
    }
}