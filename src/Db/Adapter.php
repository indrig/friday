<?php
namespace Friday\Db;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\Component;
use Friday\Base\Exception\NotSupportedException;
use Friday\Cache\AbstractCache;

class Adapter extends Component
{
    /**
     * @event Event an event that is triggered after a DB connection is established
     */
    const EVENT_AFTER_OPEN = 'afterOpen';
    /**
     * @event Event an event that is triggered right before a top-level transaction is started
     */
    const EVENT_BEGIN_TRANSACTION = 'beginTransaction';
    /**
     * @event Event an event that is triggered right after a top-level transaction is committed
     */
    const EVENT_COMMIT_TRANSACTION = 'commitTransaction';
    /**
     * @event Event an event that is triggered right after a top-level transaction is rolled back
     */
    const EVENT_ROLLBACK_TRANSACTION = 'rollbackTransaction';

    /**
     * @var Schema the database schema
     */
    private $_schema;

    /**
     * @var string the class used to create new database [[Command]] objects. If you want to extend the [[Command]] class,
     * you may configure this property to use your extended version of the class.
     * @see createCommand
     */
    public $commandClass = 'Friday\Db\Command';

    /**
     *
     */
    public $host = 'localhost';

    /**
     * @var string the username for establishing DB connection. Defaults to `null` meaning no username to use.
     */
    public $username = 'root';

    /**
     * @var string the password for establishing DB connection. Defaults to `null` meaning no password to use.
     */
    public $password = '';

    /**
     * @var string the password for establishing DB connection. Defaults to `null` meaning no password to use.
     */
    public $database;

    /**
     * @var string the common prefix or suffix for table names. If a table name is given
     * as `{{%TableName}}`, then the percentage character `%` will be replaced with this
     * property value. For example, `{{%post}}` becomes `{{tbl_post}}`.
     */
    public $tablePrefix = '';

    /**
     * @var string
     */
    public $charset = 'utf-8';

    /**
     * @var string
     */
    public $_driverName = 'mysqli';
    /**
     * @var ConnectionPool|null
     */
    private $_connectionPool;

    /**
     * @var ClientInterface
     */
    protected $_client;

    /**
     * @var boolean whether to enable schema caching.
     * Note that in order to enable truly schema caching, a valid cache component as specified
     * by [[schemaCache]] must be enabled and [[enableSchemaCache]] must be set true.
     * @see schemaCacheDuration
     * @see schemaCacheExclude
     * @see schemaCache
     */
    public $enableSchemaCache = false;
    /**
     * @var integer number of seconds that table metadata can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * @see enableSchemaCache
     */
    public $schemaCacheDuration = 3600;
    /**
     * @var array list of tables whose metadata should NOT be cached. Defaults to empty array.
     * The table names may contain schema prefix, if any. Do not quote the table names.
     * @see enableSchemaCache
     */
    public $schemaCacheExclude = [];
    /**
     * @var Friday\Cache\AbstractCache|string the cache object or the ID of the cache application component that
     * is used to cache the table metadata.
     * @see enableSchemaCache
     */
    public $schemaCache = 'cache';

    /**
     * @var boolean whether to enable query caching.
     * Note that in order to enable query caching, a valid cache component as specified
     * by [[queryCache]] must be enabled and [[enableQueryCache]] must be set true.
     * Also, only the results of the queries enclosed within [[cache()]] will be cached.
     * @see queryCache
     * @see cache()
     * @see noCache()
     */
    public $enableQueryCache = true;
    /**
     * @var integer the default number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * Defaults to 3600, meaning 3600 seconds, or one hour. Use 0 to indicate that the cached data will never expire.
     * The value of this property will be used when [[cache()]] is called without a cache duration.
     * @see enableQueryCache
     * @see cache()
     */
    public $queryCacheDuration = 3600;
    /**
     * @var AbstractCache|string the cache object or the ID of the cache application component
     * that is used for query caching.
     * @see enableQueryCache
     */
    public $queryCache = 'cache';

    /**
     * @var array query cache parameters for the [[cache()]] calls
     */
    private $_queryCacheInfo = [];


    protected $clientMap = [
        'mysqli' => 'Friday\Db\Mysqli\Client'
    ];

    /**
     * Creates a command for execution.
     * @param string $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     * @return Command the DB command
     */
    public function createCommand($sql = null, $params = [])
    {
        /** @var Command $command */
        $command = new $this->commandClass([
            'adapter'   => $this,
            'sql'       => $sql,
        ]);

        return $command->bindValues($params);
    }

    /**
     * @param string $driverName
     *
     * @return $this
     */
    public function setDriverName(string $driverName){
        $this->_driverName = strtolower($driverName);

        return $this;
    }

    /**
     * @return string
     */
    public function getDriverName(){
        return $this->_driverName;
    }

    /**
     * @return ClientInterface
     * @throws NotSupportedException
     */
    public function getClient(){
        if($this->_client === null) {
            $driver = $this->getDriverName();
            if (isset($this->clientMap[$driver])) {
                $class = $this->clientMap[$driver];

                if (class_exists($class)) {
                    if(is_string($this->clientMap[$driver])) {
                        $this->_client = Friday::createObject(['class' => $this->clientMap[$driver]]);
                    } elseif(is_array($class)) {
                            $this->_client = Friday::createObject([$this->clientMap[$driver]]);
                    } elseif (is_callable($this->clientMap[$driver])) {
                        $this->_client = call_user_func($this->clientMap[$driver]);
                    } else {
                        throw new NotSupportedException("Connection does not support reading client information for '$driver'.");

                    }
                } else {
                    throw new NotSupportedException("Connection does not support reading client information for '$driver'.");
                }
            }
        }


        return $this->_client;
    }

    /**
     * Returns the schema information for the database opened by this connection.
     * @return Schema the schema information for the database opened by this connection.
     * @throws NotSupportedException if there is no support for the current driver type
     */
    public function getSchema()
    {
        if ($this->_schema === null) {
                $client = $this->getClient();

            $this->_schema = $client->createSchema();
        }


        return $this->_schema;
    }

    /**
     * Returns the query builder for the current DB connection.
     * @return QueryBuilder the query builder for the current DB connection.
     */
    public function getQueryBuilder()
    {
        return $this->getSchema()->getQueryBuilder();
    }

    /**
     * Obtains the schema information for the named table.
     * @param string $name table name.
     * @param boolean $refresh whether to reload the table schema even if it is found in the cache.
     * @return TableSchema table schema information. Null if the named table does not exist.
     */
    public function getTableSchema($name, $refresh = false)
    {
        return $this->getSchema()->getTableSchema($name, $refresh);
    }

    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $value string to be quoted
     * @return string the properly quoted string
     * @see http://www.php.net/manual/en/function.PDO-quote.php
     */
    public function quoteValue($value)
    {
        return $this->getSchema()->quoteValue($value);
    }

    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quoteTableName($name)
    {
        return $this->getSchema()->quoteTableName($name);
    }

    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quoteColumnName($name)
    {
        return $this->getSchema()->quoteColumnName($name);
    }

    public function getConnectionPool(){
        if ($this->_connectionPool !== null) {
            return $this->_connectionPool;
        } else {
            return $this->_connectionPool = Friday::createObject([
                'class' => 'Friday\Db\ConnectionPool',
                'adapter' => $this
            ]);
        }
    }

    /**
     * Processes a SQL statement by quoting table and column names that are enclosed within double brackets.
     * Tokens enclosed within double curly brackets are treated as table names, while
     * tokens enclosed within double square brackets are column names. They will be quoted accordingly.
     * Also, the percentage character "%" at the beginning or ending of a table name will be replaced
     * with [[tablePrefix]].
     * @param string $sql the SQL to be quoted
     * @return string the quoted SQL
     */
    public function quoteSql($sql)
    {
        return preg_replace_callback(
            '/(\\{\\{(%?[\w\-\. ]+%?)\\}\\}|\\[\\[([\w\-\. ]+)\\]\\])/',
            function ($matches) {
                if (isset($matches[3])) {
                    return $this->quoteColumnName($matches[3]);
                } else {
                    return str_replace('%', $this->tablePrefix, $this->quoteTableName($matches[2]));
                }
            },
            $sql
        );
    }



    /**
     * Returns the PDO instance for the currently active slave connection.
     * When [[enableSlaves]] is true, one of the slaves will be used for read queries, and its PDO instance
     * will be returned by this method.
     * @param boolean $fallbackToMaster whether to return a master PDO in case none of the slave connections is available.
     * @return AbstractConnection the Connection instance for the currently active slave connection. Null is returned if no slave connection
     * is available and `$fallbackToMaster` is false.
     */
    public function getSlaveConnection(bool $fallbackToMaster = true) : Awaitable
    {
        /*$db = $this->getSlave(false);
        if ($db === null) {
            return $fallbackToMaster ? $this->getMasterConnection() : null;
        } else {
            return $db->pdo;
        }*/
        return $this->getMasterConnection();
    }

    /**
     * Returns the PDO instance for the currently active master connection.
     * This method will open the master DB connection and then return [[pdo]].
     * @return AbstractConnection the PDO instance for the currently active master connection.
     */
    public function getMasterConnection() : Awaitable
    {
        return $this->getConnectionPool()->getConnection();
        //$this->open();
        //return $this->pdo;
    }

    /**
     * Returns the current query cache information.
     * This method is used internally by [[Command]].
     * @param int|null $duration the preferred caching duration. If null, it will be ignored.
     * @param \Friday\Cache\AbstractDependency $dependency the preferred caching dependency. If null, it will be ignored.
     * @return array the current query cache information, or null if query cache is not enabled.
     * @internal
     */
    public function getQueryCacheInfo($duration, $dependency)
    {
        if (!$this->enableQueryCache) {
            return null;
        }

        $info = end($this->_queryCacheInfo);
        if (is_array($info)) {
            if ($duration === null) {
                $duration = $info[0];
            }
            if ($dependency === null) {
                $dependency = $info[1];
            }
        }

        if ($duration === 0 || $duration > 0) {
            if (is_string($this->queryCache) && Friday::$app) {
                $cache = Friday::$app->get($this->queryCache, false);
            } else {
                $cache = $this->queryCache;
            }
            if ($cache instanceof AbstractCache) {
                return [$cache, $duration, $dependency];
            }
        }

        return null;
    }
}