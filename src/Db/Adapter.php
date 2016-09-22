<?php
namespace Friday\Db;

use Friday;
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
    public $host;

    /**
     * @var string the username for establishing DB connection. Defaults to `null` meaning no username to use.
     */
    public $username;

    /**
     * @var string the password for establishing DB connection. Defaults to `null` meaning no password to use.
     */
    public $password;

    /**
     * @var string the password for establishing DB connection. Defaults to `null` meaning no password to use.
     */
    public $database;

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
            'db' => $this,
            'sql' => $sql,
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

    public function getFactory(){
        if($this->_factory === null) {
            $driver = $this->getDriverName();
            if (isset($this->driverFactoryMap[$driver])) {
                $class = $this->driverFactoryMap[$driver];

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
        if ($this->_schema !== null) {
            return $this->_schema;
        } else {
            $driver = $this->getDriverName();
            if (isset($this->driverFactoryMap[$driver])) {
                $class = $this->driverFactoryMap[$driver];

                if(class_exists($class)){

                } else {
                    throw new NotSupportedException("Connection does not support reading schema information for '$driver' DBMS.");
                }
                //return $this->_schema = Friday::createObject($config);
            } else {
                throw new NotSupportedException("Connection does not support reading schema information for '$driver' DBMS.");
            }
        }
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
}