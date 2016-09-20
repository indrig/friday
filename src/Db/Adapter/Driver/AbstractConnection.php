<?php
namespace Friday\Db\Adapter\Driver;


abstract class AbstractConnection implements ConnectionInterface
{
    /**
     * @var array
     */
    protected $connectionParameters = [];

    /**
     * @var string|null
     */
    protected $driverName;

    /**
     * @var boolean
     */
    protected $inTransaction = false;

    /**
     * Nested transactions count.
     *
     * @var integer
     */
    protected $nestedTransactionsCount = 0;


    /**
     * @var resource|null
     */
    protected $resource;

    /**
     * {@inheritDoc}
     */
    public function disconnect()
    {
        if ($this->isConnected()) {
            $this->resource = null;
        }

        return $this;
    }

    /**
     * Get connection parameters
     *
     * @return array
     */
    public function getConnectionParameters()
    {
        return $this->connectionParameters;
    }

    /**
     * Get driver name
     *
     * @return null|string
     */
    public function getDriverName()
    {
        return $this->driverName;
    }

    /**
     * {@inheritDoc}
     *
     * @return resource
     */
    public function getResource()
    {
        if (!$this->isConnected()) {
            $this->connect();
        }

        return $this->resource;
    }

    /**
     * Checks whether the connection is in transaction state.
     *
     * @return boolean
     */
    public function inTransaction()
    {
        return $this->inTransaction;
    }

    /**
     * @param  array $connectionParameters
     * @return self
     */
    public function setConnectionParameters(array $connectionParameters)
    {
        $this->connectionParameters = $connectionParameters;

        return $this;
    }
}
