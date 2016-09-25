<?php
namespace Friday\Db;

use Friday;
use Friday\Base\Awaitable;
use Friday\Base\Component;
use Friday\Base\Deferred;
use Friday\Base\Exception\NotSupportedException;
use Friday\Db\Exception\Exception;
use Friday\Helper\AwaitableHelper;


use Throwable;

/**
 * Command represents a SQL statement to be executed against a database.
 *
 * A command object is usually created by calling [[Connection::createCommand()]].
 * The SQL statement it represents can be set via the [[sql]] property.
 *
 * To execute a non-query SQL (such as INSERT, DELETE, UPDATE), call [[execute()]].
 * To execute a SQL statement that returns a result data set (such as SELECT),
 * use [[queryAll()]], [[queryOne()]], [[queryColumn()]], [[queryScalar()]], or [[query()]].
 *
 * For example,
 *
 * ```php
 * $users = $connection->createCommand('SELECT * FROM user')->queryAll();
 * ```
 *
 * Command supports SQL statement preparation and parameter binding.
 * Call [[bindValue()]] to bind a value to a SQL parameter;
 * Call [[bindParam()]] to bind a PHP variable to a SQL parameter.
 * When binding a parameter, the SQL statement is automatically prepared.
 * You may also call [[prepare()]] explicitly to prepare a SQL statement.
 *
 * Command also supports building SQL statements by providing methods such as [[insert()]],
 * [[update()]], etc. For example, the following code will create and execute an INSERT SQL statement:
 *
 * ```php
 * $connection->createCommand()->insert('user', [
 *     'name' => 'Sam',
 *     'age' => 30,
 * ])->execute();
 * ```
 *
 * To build SELECT SQL statements, please use [[Query]] instead.
 *
 * For more details and usage information on Command, see the [guide article on Database Access Objects](guide:db-dao).
 *
 * @property string $rawSql The raw SQL with parameter values inserted into the corresponding placeholders in
 * [[sql]]. This property is read-only.
 * @property string $sql The SQL statement to be executed.
 */
class Command extends Component
{
    /**
     * @var Adapter the DB connection that this command is associated with
     */
    public $adapter;

    /**
     * @var AbstractConnection
     */
    public $connection;
    /**
     * @var AbstractStatement the Statement object that this command is associated with
     */
    public $statement;
    /**
     * @var integer the default fetch mode for this command.
     */
    public $fetchMode = AbstractStatement::FETCH_ASSOC;
    /**
     * @var array the parameters (name => value) that are bound to the current PDO statement.
     * This property is maintained by methods such as [[bindValue()]]. It is mainly provided for logging purpose
     * and is used to generate [[rawSql]]. Do not modify it directly.
     */
    public $params = [];
    /**
     * @var integer the default number of seconds that query results can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire. And use a negative number to indicate
     * query cache should not be used.
     * @see cache()
     */
    public $queryCacheDuration;
    /**
     * @var \Friday\Cache\AbstractDependency the dependency to be associated with the cached query result for this command
     * @see cache()
     */
    public $queryCacheDependency;

    /**
     * @var array pending parameters to be bound to the current Statement.
     */
    private $_pendingParams = [];

    /**
     * @var string the SQL statement that this command represents
     */
    private $_sql;
    /**
     * @var string name of the table, which schema, should be refreshed after command execution.
     */
    private $_refreshTableName;


    /**
     * Enables query cache for this command.
     * @param integer $duration the number of seconds that query result of this command can remain valid in the cache.
     * If this is not set, the value of [[Connection::queryCacheDuration]] will be used instead.
     * Use 0 to indicate that the cached data will never expire.
     * @param \Friday\Cache\AbstractDependency $dependency the cache dependency associated with the cached query result.
     * @return $this the command object itself
     */
    public function cache($duration = null, $dependency = null)
    {
        $this->queryCacheDuration = $duration === null ? $this->db->queryCacheDuration : $duration;
        $this->queryCacheDependency = $dependency;
        return $this;
    }

    /**
     * Disables query cache for this command.
     * @return $this the command object itself
     */
    public function noCache()
    {
        $this->queryCacheDuration = -1;
        return $this;
    }

    /**
     * Returns the SQL statement for this command.
     * @return string the SQL statement to be executed
     */
    public function getSql()
    {
        return $this->_sql;
    }

    /**
     * Specifies the SQL statement to be executed.
     * The previous SQL execution (if any) will be cancelled, and [[params]] will be cleared as well.
     * @param string $sql the SQL statement to be set.
     * @return $this this command instance
     */
    public function setSql($sql)
    {
        if ($sql !== $this->_sql) {
            $this->cancel();
            $this->_sql = $this->adapter->quoteSql($sql);
            $this->_pendingParams = [];
            $this->params = [];
            $this->_refreshTableName = null;
        }

        return $this;
    }

    /**
     * Returns the raw SQL by inserting parameter values into the corresponding placeholders in [[sql]].
     * Note that the return value of this method should mainly be used for logging purpose.
     * It is likely that this method returns an invalid SQL due to improper replacement of parameter placeholders.
     * @return string the raw SQL with parameter values inserted into the corresponding placeholders in [[sql]].
     */
    public function getRawSql()
    {
        if (empty($this->params)) {
            return $this->_sql;
        }
        $params = [];
        foreach ($this->params as $name => $value) {
            if (is_string($name) && strncmp(':', $name, 1)) {
                $name = ':' . $name;
            }
            if (is_string($value)) {
                $params[$name] = $this->adapter->quoteValue($value);
            } elseif (is_bool($value)) {
                $params[$name] = ($value ? 'TRUE' : 'FALSE');
            } elseif ($value === null) {
                $params[$name] = 'NULL';
            } elseif (!is_object($value) && !is_resource($value)) {
                $params[$name] = $value;
            }
        }
        if (!isset($params[1])) {
            return strtr($this->_sql, $params);
        }
        $sql = '';
        foreach (explode('?', $this->_sql) as $i => $part) {
            $sql .= (isset($params[$i]) ? $params[$i] : '') . $part;
        }

        return $sql;
    }

    /**
     * Prepares the SQL statement to be executed.
     * For complex SQL statement that is to be executed multiple times,
     * this may improve performance.
     * For SQL statement with binding parameters, this method is invoked
     * automatically.
     * @param boolean $forRead whether this method is called for a read query. If null, it means
     * the SQL statement should be used to determine whether it is for read or write.
     * @return Awaitable
     */
    public function prepare($forRead = null) : Awaitable
    {
        if ($this->statement) {
            $this->bindPendingParams();
            return AwaitableHelper::result();
        }

        $deferred = new Deferred();
        $sql = $this->getSql();

        //  if ($this->db->getTransaction()) {
        // master is in a transaction. use the same connection.
        //    $forRead = false;
        //  }

        (($forRead || $forRead === null && $this->adapter->getSchema()->isReadQuery($sql)) ? $this->adapter->getSlaveConnection() : $this->adapter->getMasterConnection())
            ->await(function ($result) use ($sql, $deferred) {
                if ($result instanceof Throwable) {
                    $deferred->result($result);
                } else {
                    $this->connection = $result;
                    try {
                        $this->statement = $this->connection->prepare($sql);
                        $this->bindPendingParams();
                        $deferred->result();
                    } catch (Throwable $e) {
                        $message = $e->getMessage() . "\nFailed to prepare SQL: $sql";
                        $deferred->exception(new Exception($message, null, (int)$e->getCode(), $e));
                    }
                }
            });


        return $deferred->awaitable();
    }

    /**
     * Cancels the execution of the SQL statement.
     * This method mainly sets [[pdoStatement]] to be null.
     */
    public function cancel()
    {
        $this->statement = null;
    }

    /**
     * Binds a parameter to the SQL statement to be executed.
     * @param string|integer $name parameter identifier. For a prepared statement
     * using named placeholders, this will be a parameter name of
     * the form `:name`. For a prepared statement using question mark
     * placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value the PHP variable to bind to the SQL statement parameter (passed by reference)
     * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @param integer $length length of the data type
     * @return Awaitable
     * @see http://www.php.net/manual/en/function.PDOStatement-bindParam.php
     */
    /*public function bindParam($name, &$value, $dataType = null, $length = null) : Awaitable
    {
        $deferred = new Deferred();
        $this->prepare()->await(function ($result) use ($deferred, &$name, &$value, &$dataType, &$length) {
            if ($result instanceof Throwable) {
                $deferred->exception($result);
            } else {
                if ($dataType === null) {
                    $dataType = $this->adapter->getSchema()->getType($value);
                }
                if ($length === null) {
                    $this->statement->bindParam($name, $value, $dataType);
                } else {
                    $this->statement->bindParam($name, $value, $dataType, $length);
                }
                $this->params[$name] = &$value;

                $deferred->result($result);
            }
        });

        return $deferred->awaitable();
    }*/

    /**
     * Binds pending parameters that were registered via [[bindValue()]] and [[bindValues()]].
     * Note that this method requires an active [[Statement]].
     */
    protected function bindPendingParams()
    {
        foreach ($this->_pendingParams as $name => $value) {
            $this->statement->bindValue($name, $value[0], $value[1]);
        }
        $this->_pendingParams = [];
    }

    /**
     * Binds a value to a parameter.
     * @param string|integer $name Parameter identifier. For a prepared statement
     * using named placeholders, this will be a parameter name of
     * the form `:name`. For a prepared statement using question mark
     * placeholders, this will be the 1-indexed position of the parameter.
     * @param mixed $value The value to bind to the parameter
     * @param integer $dataType SQL data type of the parameter. If null, the type is determined by the PHP type of the value.
     * @return $this the current command being executed
     * @see http://www.php.net/manual/en/function.PDOStatement-bindValue.php
     */
    public function bindValue($name, $value, $dataType = null)
    {
        if ($dataType === null) {
            $dataType = $this->adapter->getSchema()->getType($value);
        }
        $this->_pendingParams[$name] = [$value, $dataType];
        $this->params[$name] = $value;

        return $this;
    }

    /**
     * Binds a list of values to the corresponding parameters.
     * This is similar to [[bindValue()]] except that it binds multiple values at a time.
     * Note that the SQL data type of each value is determined by its PHP type.
     * @param array $values the values to be bound. This must be given in terms of an associative
     * array with array keys being the parameter names, and array values the corresponding parameter values,
     * e.g. `[':name' => 'John', ':age' => 25]`. By default, the PDO type of each value is determined
     * by its PHP type. You may explicitly specify the PDO type by using an array: `[value, type]`,
     * e.g. `[':name' => 'John', ':profile' => [$profile, \PDO::PARAM_LOB]]`.
     * @return $this the current command being executed
     */
    public function bindValues($values)
    {
        if (empty($values)) {
            return $this;
        }

        $schema = $this->adapter->getSchema();
        foreach ($values as $name => $value) {
            if (is_array($value)) {
                $this->_pendingParams[$name] = $value;
                $this->params[$name] = $value[0];
            } else {
                $type = $schema->getType($value);
                $this->_pendingParams[$name] = [$value, $type];
                $this->params[$name] = $value;
            }
        }

        return $this;
    }

    /**
     * Executes the SQL statement and returns query result.
     * This method is for executing a SQL query that returns result set, such as `SELECT`.
     * @return Awaitable
     * @throws Exception execution failed
     */
    public function query() : Awaitable
    {
        return $this->queryInternal('');
    }

    /**
     * Executes the SQL statement and returns ALL rows at once.
     * @param integer $fetchMode the result fetch mode. Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return Awaitable array all rows of the query result. Each array element is an array representing a row of data.
     * An empty array is returned if the query results in nothing.
     * @throws Exception execution failed
     */
    public function queryAll($fetchMode = null) : Awaitable
    {
        return $this->queryInternal('fetchAll', $fetchMode);
    }

    /**
     * Executes the SQL statement and returns the first row of the result.
     * This method is best used when only the first row of result is needed for a query.
     * @param integer $fetchMode the result fetch mode. Please refer to [PHP manual](http://www.php.net/manual/en/function.PDOStatement-setFetchMode.php)
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return Awaitable array|false the first row (in terms of an array) of the query result. False is returned if the query
     * results in nothing.
     * @throws Exception execution failed
     */
    public function queryOne($fetchMode = null) : Awaitable
    {
        return $this->queryInternal('fetch', $fetchMode);
    }

    /**
     * Executes the SQL statement and returns the value of the first column in the first row of data.
     * This method is best used when only a single value is needed for a query.
     * @param int $columnNumber
     * @return Awaitable string|null|false the value of the first column in the first row of the query result.
     * False is returned if there is no value.
     * @throws Exception execution failed
     */
    public function queryScalar($columnNumber = 0) : Awaitable
    {
        return $this->queryInternal('fetchScalar', $columnNumber);

    }

    /**
     * Executes the SQL statement and returns the first column of the result.
     * This method is best used when only the first column of result (i.e. the first element in each row)
     * is needed for a query.
     * @param int $columnNumber
     * @return Awaitable array the first column of the query result. Empty array is returned if the query results in nothing.
     * @throws Awaitable
     */
    public function queryColumn($columnNumber = 0) : Awaitable
    {
        return $this->queryInternal('fetchColumn', $columnNumber);
    }

    /**
     * Creates an INSERT command.
     * For example,
     *
     * ```php
     * $connection->createCommand()->insert('user', [
     *     'name' => 'Sam',
     *     'age' => 30,
     * ])->execute();
     * ```
     *
     * The method will properly escape the column names, and bind the values to be inserted.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column data (name => value) to be inserted into the table.
     * @return $this the command object itself
     */
    public function insert($table, $columns)
    {
        $params = [];
        $sql = $this->adapter->getQueryBuilder()->insert($table, $columns, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a batch INSERT command.
     * For example,
     *
     * ```php
     * $connection->createCommand()->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ])->execute();
     * ```
     *
     * The method will properly escape the column names, and quote the values to be inserted.
     *
     * Note that the values in each row must match the corresponding column names.
     *
     * Also note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names
     * @param array $rows the rows to be batch inserted into the table
     * @return $this the command object itself
     */
    public function batchInsert($table, $columns, $rows)
    {
        $sql = $this->adapter->getQueryBuilder()->batchInsert($table, $columns, $rows);

        return $this->setSql($sql);
    }

    /**
     * Creates an UPDATE command.
     * For example,
     *
     * ```php
     * $connection->createCommand()->update('user', ['status' => 1], 'age > 30')->execute();
     * ```
     *
     * The method will properly escape the column names and bind the values to be updated.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table to be updated.
     * @param array $columns the column data (name => value) to be updated.
     * @param string|array $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array $params the parameters to be bound to the command
     * @return $this the command object itself
     */
    public function update($table, $columns, $condition = '', $params = [])
    {
        $sql = $this->adapter->getQueryBuilder()->update($table, $columns, $condition, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a DELETE command.
     * For example,
     *
     * ```php
     * $connection->createCommand()->delete('user', 'status = 0')->execute();
     * ```
     *
     * The method will properly escape the table and column names.
     *
     * Note that the created command is not executed until [[execute()]] is called.
     *
     * @param string $table the table where the data will be deleted from.
     * @param string|array $condition the condition that will be put in the WHERE part. Please
     * refer to [[Query::where()]] on how to specify condition.
     * @param array $params the parameters to be bound to the command
     * @return $this the command object itself
     */
    public function delete($table, $condition = '', $params = [])
    {
        $sql = $this->adapter->getQueryBuilder()->delete($table, $condition, $params);

        return $this->setSql($sql)->bindValues($params);
    }

    /**
     * Creates a SQL command for creating a new DB table.
     *
     * The columns in the new table should be specified as name-definition pairs (e.g. 'name' => 'string'),
     * where name stands for a column name which will be properly quoted by the method, and definition
     * stands for the column type which can contain an abstract DB type.
     * The method [[QueryBuilder::getColumnType()]] will be called
     * to convert the abstract column types to physical ones. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     *
     * If a column is specified with definition only (e.g. 'PRIMARY KEY (name, type)'), it will be directly
     * inserted into the generated SQL.
     *
     * @param string $table the name of the table to be created. The name will be properly quoted by the method.
     * @param array $columns the columns (name => definition) in the new table.
     * @param string $options additional SQL fragment that will be appended to the generated SQL.
     * @return $this the command object itself
     */
    public function createTable($table, $columns, $options = null)
    {
        $sql = $this->adapter->getQueryBuilder()->createTable($table, $columns, $options);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL command for renaming a DB table.
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function renameTable($table, $newName)
    {
        $sql = $this->adapter->getQueryBuilder()->renameTable($table, $newName);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a DB table.
     * @param string $table the table to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropTable($table)
    {
        $sql = $this->adapter->getQueryBuilder()->dropTable($table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function truncateTable($table)
    {
        $sql = $this->adapter->getQueryBuilder()->truncateTable($table);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL command for adding a new DB column.
     * @param string $table the table that the new column will be added to. The table name will be properly quoted by the method.
     * @param string $column the name of the new column. The name will be properly quoted by the method.
     * @param string $type the column type. [[\yii\db\QueryBuilder::getColumnType()]] will be called
     * to convert the give column type to the physical one. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     * @return $this the command object itself
     */
    public function addColumn($table, $column, $type)
    {
        $sql = $this->adapter->getQueryBuilder()->addColumn($table, $column, $type);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for dropping a DB column.
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropColumn($table, $column)
    {
        $sql = $this->adapter->getQueryBuilder()->dropColumn($table, $column);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function renameColumn($table, $oldName, $newName)
    {
        $sql = $this->adapter->getQueryBuilder()->renameColumn($table, $oldName, $newName);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the column type. [[\yii\db\QueryBuilder::getColumnType()]] will be called
     * to convert the give column type to the physical one. For example, `string` will be converted
     * as `varchar(255)`, and `string not null` becomes `varchar(255) not null`.
     * @return $this the command object itself
     */
    public function alterColumn($table, $column, $type)
    {
        $sql = $this->adapter->getQueryBuilder()->alterColumn($table, $column, $type);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a primary key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     * @return $this the command object itself.
     */
    public function addPrimaryKey($name, $table, $columns)
    {
        $sql = $this->adapter->getQueryBuilder()->addPrimaryKey($name, $table, $columns);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for removing a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return $this the command object itself
     */
    public function dropPrimaryKey($name, $table)
    {
        $sql = $this->adapter->getQueryBuilder()->dropPrimaryKey($name, $table);

        return $this->setSql($sql)->requireTableSchemaRefresh($table);
    }

    /**
     * Creates a SQL command for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on. If there are multiple columns, separate them with commas.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to. If there are multiple columns, separate them with commas.
     * @param string $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @return $this the command object itself
     */
    public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete = null, $update = null)
    {
        $sql = $this->adapter->getQueryBuilder()->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL command for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropForeignKey($name, $table)
    {
        $sql = $this->adapter->getQueryBuilder()->dropForeignKey($name, $table);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL command for creating a new index.
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns, please separate them
     * by commas. The column names will be properly quoted by the method.
     * @param boolean $unique whether to add UNIQUE constraint on the created index.
     * @return $this the command object itself
     */
    public function createIndex($name, $table, $columns, $unique = false)
    {
        $sql = $this->adapter->getQueryBuilder()->createIndex($name, $table, $columns, $unique);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL command for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return $this the command object itself
     */
    public function dropIndex($name, $table)
    {
        $sql = $this->adapter->getQueryBuilder()->dropIndex($name, $table);

        return $this->setSql($sql);
    }

    /**
     * Creates a SQL command for resetting the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or 1.
     * @param string $table the name of the table whose primary key sequence will be reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have a value 1.
     * @return $this the command object itself
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     */
    public function resetSequence($table, $value = null)
    {
        $sql = $this->adapter->getQueryBuilder()->resetSequence($table, $value);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for enabling or disabling integrity check.
     * @param boolean $check whether to turn on or off the integrity check.
     * @param string $schema the schema name of the tables. Defaults to empty string, meaning the current
     * or default schema.
     * @param string $table the table name.
     * @return $this the command object itself
     * @throws NotSupportedException if this is not supported by the underlying DBMS
     */
    public function checkIntegrity($check = true, $schema = '', $table = '')
    {
        $sql = $this->adapter->getQueryBuilder()->checkIntegrity($check, $schema, $table);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for adding comment to column
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return $this the command object itself
     * @since 2.0.8
     */
    public function addCommentOnColumn($table, $column, $comment)
    {
        $sql = $this->adapter->getQueryBuilder()->addCommentOnColumn($table, $column, $comment);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for adding comment to table
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @return $this the command object itself
     * @since 2.0.8
     */
    public function addCommentOnTable($table, $comment)
    {
        $sql = $this->adapter->getQueryBuilder()->addCommentOnTable($table, $comment);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for dropping comment from column
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be commented. The column name will be properly quoted by the method.
     * @return $this the command object itself
     * @since 2.0.8
     */
    public function dropCommentFromColumn($table, $column)
    {
        $sql = $this->adapter->getQueryBuilder()->dropCommentFromColumn($table, $column);

        return $this->setSql($sql);
    }

    /**
     * Builds a SQL command for dropping comment from table
     *
     * @param string $table the table whose column is to be commented. The table name will be properly quoted by the method.
     * @return $this the command object itself
     * @since 2.0.8
     */
    public function dropCommentFromTable($table)
    {
        $sql = $this->adapter->getQueryBuilder()->dropCommentFromTable($table);

        return $this->setSql($sql);
    }

    /**
     * Executes the SQL statement.
     * This method should only be used for executing non-query SQL statement, such as `INSERT`, `DELETE`, `UPDATE` SQLs.
     * No result set will be returned.
     * @return Awaitable integer number of rows affected by the execution.
     */
    public function execute() : Awaitable
    {
        $sql = $this->getSql();

        $rawSql = $this->getRawSql();

        Friday::info($rawSql, __METHOD__);

        if ($sql == '') {
            return AwaitableHelper::result(0);
        }

        $deferred = new Deferred();

        $this->prepare(false)->await(function ($result) use ($deferred, &$rawSql) {
            if ($result instanceof Throwable) {
                echo($result);
                $deferred->exception($result);
            } else {
                $token = $rawSql;
                Friday::beginProfile($token, __METHOD__);

                $this->statement->execute()->await(function ($result) use ($deferred, &$token) {
                    if ($result instanceof Throwable) {
                        Friday::endProfile($token, __METHOD__);
                        $deferred->exception($this->adapter->getSchema()->convertException($result, $token));
                    } else {
                        $n = $this->statement->rowCount();
                        Friday::endProfile($token, __METHOD__);

                        $this->refreshTableSchema();

                        $deferred->result($n);
                    }
                });
            }
        });

        return $deferred->awaitable();
    }

    /**
     * Performs the actual DB query of a SQL statement.
     * @param string $method method of PDOStatement to be called
     * @param integer $fetchMode the result fetch mode.
     * for valid fetch modes. If this parameter is null, the value set in [[fetchMode]] will be used.
     * @return Awaitable the method execution result
     * @throws Exception if the query causes any problem
     */
    protected function queryInternal($method, $fetchMode = null) : Awaitable
    {
        $deferred = new Deferred();
        $rawSql = $this->getRawSql();

        Friday::info($rawSql, 'Friday\Db\Command::query');

        if ($method !== '') {
            $info = $this->adapter->getQueryCacheInfo($this->queryCacheDuration, $this->queryCacheDependency);
            if (is_array($info)) {
                /* @var $cache \Friday\Cache\AbstractCache */
                $cache = $info[0];
                $cacheKey = [
                    __CLASS__,
                    $method,
                    $fetchMode,
                    $this->adapter->host,
                    $this->adapter->database,
                    $this->adapter->username,
                    $rawSql,
                ];
                $cache->get($cacheKey)->await(function ($result) use ($deferred, &$rawSql, &$rawSql, &$method, &$fetchMode, &$info, &$cacheKey, &$cache) {
                    if (is_array($result) && isset($result[0])) {
                        Friday::trace('Query result served from cache', 'Friday\Db\Command::query');
                        $deferred->result($result[0]);
                    } else {
                        $this->prepare(true);

                        Friday::beginProfile($rawSql, 'Friday\Db\Command::query');

                        $this->statement->execute()->await(function ($result) use ($deferred, &$rawSql, &$method, &$fetchMode, &$info, &$cacheKey, &$cache) {

                            if($result instanceof Throwable) {
                                $deferred->exception($result);
                            } else {
                                try {
                                    if ($method === '') {
                                        $data = new DataReader($this);
                                    } else {
                                        if ($fetchMode === null) {
                                            $fetchMode = $this->fetchMode;
                                        }
                                        $data = call_user_func_array([$this->statement, $method], (array)$fetchMode);
                                        $this->statement->closeCursor();
                                    }
                                    Friday::endProfile($rawSql, 'Friday\Db\Command::query');
                                } catch (Throwable $e) {
                                    Friday::endProfile($rawSql, 'Friday\Db\Command::query');
                                    $deferred->exception($this->adapter->getSchema()->convertException($e, $rawSql));
                                    return;
                                }


                                $cache->set($cacheKey, [$result], $info[1], $info[2])->await(function () use ($deferred, &$data){
                                    $deferred->result($data);
                                    Friday::trace('Saved query result in cache', 'yii\db\Command::query');
                                });
                            }
                        });
                    }
                });

            } else {
                $this->prepare(true);

                Friday::beginProfile($rawSql, 'Friday\Db\Command::query');

                $this->statement->execute()->await(function ($result) use ($deferred, &$rawSql, &$method, &$fetchMode) {

                    if($result instanceof Throwable) {
                        $deferred->exception($result);
                    } else {
                        try {
                            if ($method === '') {
                                $data = new DataReader($this);
                            } else {
                                if ($fetchMode === null) {
                                    $fetchMode = $this->fetchMode;
                                }
                                $data = call_user_func_array([$this->statement, $method], (array)$fetchMode);
                                //$this->statement->closeCursor();
                            }
                            Friday::endProfile($rawSql, 'Friday\Db\Command::query');
                        } catch (Throwable $e) {
                            Friday::endProfile($rawSql, 'Friday\Db\Command::query');
                            $deferred->exception($this->adapter->getSchema()->convertException($e, $rawSql));
                            return;
                        }

                        $deferred->result($data);
                        Friday::trace('Saved query result in cache', 'yii\db\Command::query');
                    }
                });
            }
        }

        return $deferred->awaitable();
    }

    /**
     * Marks a specified table schema to be refreshed after command execution.
     * @param string $name name of the table, which schema should be refreshed.
     * @return $this this command instance
     */
    protected function requireTableSchemaRefresh($name)
    {
        $this->_refreshTableName = $name;
        return $this;
    }

    /**
     * Refreshes table schema, which was marked by [[requireTableSchemaRefresh()]]
     * @since 2.0.6
     */
    protected function refreshTableSchema()
    {
        if ($this->_refreshTableName !== null) {
            $this->adapter->getSchema()->refreshTableSchema($this->_refreshTableName);
        }
    }
}
