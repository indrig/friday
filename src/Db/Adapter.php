<?php
namespace Friday\Db;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\Component;
use Friday\Base\Exception\NotSupportedException;

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

    protected $_factory;


    protected $driverFactoryMap = [
        'mysqli' => 'Friday\Db\Mysqli\Factory'
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
     * @return FactoryInterface
     * @throws NotSupportedException
     */
    public function getFactory(){
        if($this->_factory === null) {
            $driver = $this->getDriverName();
            if (isset($this->driverFactoryMap[$driver])) {
                $class = $this->driverFactoryMap[$driver];

                var_dump($class);
                if (class_exists($class)) {
                    if(is_string($this->driverFactoryMap[$driver])) {
                        $this->_factory = Friday::createObject(['class' => $this->driverFactoryMap[$driver]]);
                    } elseif(is_array($class)) {
                            $this->_factory = Friday::createObject([$this->driverFactoryMap[$driver]]);
                    } elseif (is_callable($this->driverFactoryMap[$driver])) {
                        $this->_factory = call_user_func($this->driverFactoryMap[$driver]);
                    } else {
                        throw new NotSupportedException("Connection does not support reading driver information for '$driver'.");

                    }
                } else {
                    throw new NotSupportedException("Connection does not support reading driver information for '$driver'.");
                }
            }
        }


        return $this->_factory;
    }

    /**
     * Returns the schema information for the database opened by this connection.
     * @return Schema the schema information for the database opened by this connection.
     * @throws NotSupportedException if there is no support for the current driver type
     */
    public function getSchema()
    {
        if ($this->_schema === null) {
            $driver = $this->getDriverName();

            $factory = $this->getFactory();

            $this->_schema = $factory->createSchema();
        }


        return $this->_schema;
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
}