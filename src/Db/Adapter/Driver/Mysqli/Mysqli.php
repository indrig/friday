<?php
namespace Friday\Db\Adapter\Driver\Mysqli;

use mysqli_stmt;
use Friday\Db\Adapter\Driver\DriverInterface;
use Friday\Db\Adapter\Exception;

class Mysqli implements DriverInterface
{
    /**
     * @var Connection
     */
    protected $connection = null;

    /**
     * @var Statement
     */
    protected $statementPrototype = null;

    /**
     * @var Result
     */
    protected $resultPrototype = null;


    /**
     * @var array
     */
    protected $options = [
        'buffer_results' => false
    ];

    /**
     * Constructor
     *
     * @param array|Connection|\mysqli $connection
     * @param null|Statement $statementPrototype
     * @param null|Result $resultPrototype
     * @param array $options
     */
    public function __construct($connection, Statement $statementPrototype = null, Result $resultPrototype = null, array $options = [])
    {
        if (!$connection instanceof Connection) {
            $connection = new Connection($connection);
        }

        $options = array_intersect_key(array_merge($this->options, $options), $this->options);

        $this->registerConnection($connection);
        $this->registerStatementPrototype(($statementPrototype) ?: new Statement($options['buffer_results']));
        $this->registerResultPrototype(($resultPrototype) ?: new Result());
    }




    /**
     * Register connection
     *
     * @param  Connection $connection
     * @return Mysqli
     */
    public function registerConnection(Connection $connection)
    {
        $this->connection = $connection;
        $this->connection->setDriver($this); // needs access to driver to createStatement()
        return $this;
    }

    /**
     * Register statement prototype
     *
     * @param Statement $statementPrototype
     */
    public function registerStatementPrototype(Statement $statementPrototype)
    {
        $this->statementPrototype = $statementPrototype;
        $this->statementPrototype->setDriver($this); // needs access to driver to createResult()
    }

    /**
     * Get statement prototype
     *
     * @return null|Statement
     */
    public function getStatementPrototype()
    {
        return $this->statementPrototype;
    }

    /**
     * Register result prototype
     *
     * @param Result $resultPrototype
     */
    public function registerResultPrototype(Result $resultPrototype)
    {
        $this->resultPrototype = $resultPrototype;
    }

    /**
     * @return null|Result
     */
    public function getResultPrototype()
    {
        return $this->resultPrototype;
    }

    /**
     * Get database platform name
     *
     * @param  string $nameFormat
     * @return string
     */
    public function getDatabasePlatformName($nameFormat = self::NAME_FORMAT_CAMELCASE)
    {
        if ($nameFormat == self::NAME_FORMAT_CAMELCASE) {
            return 'Mysql';
        }

        return 'MySQL';
    }

    /**
     * Check environment
     *
     * @throws Exception\RuntimeException
     * @return void
     */
    public function checkEnvironment()
    {
        if (!extension_loaded('mysqli')) {
            throw new Exception\RuntimeException('The Mysqli extension is required for this adapter but the extension is not loaded');
        }
    }

    /**
     * Get connection
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Create statement
     *
     * @param string $sqlOrResource
     * @return Statement
     */
    public function createStatement($sqlOrResource = null)
    {
        /**
         * @todo Resource tracking
        if (is_resource($sqlOrResource) && !in_array($sqlOrResource, $this->resources, true)) {
            $this->resources[] = $sqlOrResource;
        }
        */

        $statement = clone $this->statementPrototype;
        if ($sqlOrResource instanceof mysqli_stmt) {
            $statement->setResource($sqlOrResource);
        } else {
            if (is_string($sqlOrResource)) {
                $statement->setSql($sqlOrResource);
            }
            if (!$this->connection->isConnected()) {
                $this->connection->connect();
            }
            $statement->initialize($this->connection->getResource());
        }
        return $statement;
    }

    /**
     * Create result
     *
     * @param resource $resource
     * @param null|bool $isBuffered
     * @return Result
     */
    public function createResult($resource, $isBuffered = null)
    {
        $result = clone $this->resultPrototype;
        $result->initialize($resource, $this->connection->getLastGeneratedValue(), $isBuffered);
        return $result;
    }

    /**
     * Get prepare type
     *
     * @return string
     */
    public function getPrepareType()
    {
        return self::PARAMETERIZATION_POSITIONAL;
    }

    /**
     * Format parameter name
     *
     * @param string $name
     * @param mixed  $type
     * @return string
     */
    public function formatParameterName($name, $type = null)
    {
        return '?';
    }

    /**
     * Get last generated value
     *
     * @return mixed
     */
    public function getLastGeneratedValue()
    {
        return $this->getConnection()->getLastGeneratedValue();
    }
}
