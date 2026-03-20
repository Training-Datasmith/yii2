<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use PDO;
use Yii;
use yii\base\Component;
use yii\base\Invalid_Config_Exception;
use yii\base\Not_Supported_Exception;
use yii\caching\Cache_Interface;
/**
 * Connection represents a connection to a database via [PDO](https://www.php.net/manual/en/book.pdo.php).
 *
 * Connection works together with [[Command]], [[DataReader]] and [[Transaction]]
 * to provide data access to various DBMS in a common set of APIs. They are a thin wrapper
 * of the [PDO PHP extension](https://www.php.net/manual/en/book.pdo.php).
 *
 * Connection supports database replication and read-write splitting. In particular, a Connection component
 * can be configured with multiple [[masters]] and [[slaves]]. It will do load balancing and failover by choosing
 * appropriate servers. It will also automatically direct read operations to the slaves and write operations to
 * the masters.
 *
 * To establish a DB connection, set [[dsn]], [[username]] and [[password]], and then
 * call [[open()]] to connect to the database server. The current state of the connection can be checked using [[$isActive]].
 *
 * The following example shows how to create a Connection instance and establish
 * the DB connection:
 *
 * ```
 * $connection = new \yii\db\Connection([
 *     'dsn' => $dsn,
 *     'username' => $username,
 *     'password' => $password,
 * ]);
 * $connection->open();
 * ```
 *
 * After the DB connection is established, one can execute SQL statements like the following:
 *
 * ```
 * $command = $connection->createCommand('SELECT * FROM post');
 * $posts = $command->queryAll();
 * $command = $connection->createCommand('UPDATE post SET status=1');
 * $command->execute();
 * ```
 *
 * One can also do prepared SQL execution and bind parameters to the prepared SQL.
 * When the parameters are coming from user input, you should use this approach
 * to prevent SQL injection attacks. The following is an example:
 *
 * ```
 * $command = $connection->createCommand('SELECT * FROM post WHERE id=:id');
 * $command->bindValue(':id', $_GET['id']);
 * $post = $command->query();
 * ```
 *
 * For more information about how to perform various DB queries, please refer to [[Command]].
 *
 * If the underlying DBMS supports transactions, you can perform transactional SQL queries
 * like the following:
 *
 * ```
 * $transaction = $connection->beginTransaction();
 * try {
 *     $connection->createCommand($sql1)->execute();
 *     $connection->createCommand($sql2)->execute();
 *     // ... executing other SQL statements ...
 *     $transaction->commit();
 * } catch (Exception $e) {
 *     $transaction->rollBack();
 * }
 * ```
 *
 * You also can use shortcut for the above like the following:
 *
 * ```
 * $connection->transaction(function () {
 *     $order = new Order($customer);
 *     $order->save();
 *     $order->addItems($items);
 * });
 * ```
 *
 * If needed you can pass transaction isolation level as a second parameter:
 *
 * ```
 * $connection->transaction(function (Connection $db) {
 *     //return $db->...
 * }, Transaction::READ_UNCOMMITTED);
 * ```
 *
 * Connection is often used as an application component and configured in the application
 * configuration like the following:
 *
 * ```
 * 'components' => [
 *     'db' => [
 *         'class' => '\yii\db\Connection',
 *         'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
 *         'username' => 'root',
 *         'password' => '',
 *         'charset' => 'utf8',
 *     ],
 * ],
 * ```
 *
 * @property string|null $driverName Name of the DB driver. Note that the type of this property differs in
 * getter and setter. See [[getDriverName()]] and [[setDriverName()]] for details.
 * @property-read bool $isActive Whether the DB connection is established.
 * @property-read string $lastInsertID The row ID of the last row inserted, or the last value retrieved from
 * the sequence object.
 * @property-read Connection|null $master The currently active master connection. `null` is returned if there
 * is no master available.
 * @property-read PDO $masterPdo The PDO instance for the currently active master connection.
 * @property QueryBuilder $queryBuilder The query builder for the current DB connection. Note that the type of
 * this property differs in getter and setter. See [[getQueryBuilder()]] and [[setQueryBuilder()]] for details.
 * @property-read Schema $schema The schema information for the database opened by this
 * connection.
 * @property-read string $serverVersion Server version as a string.
 * @property-read Connection|null $slave The currently active slave connection. `null` is returned if there is
 * no slave available and `$fallbackToMaster` is false.
 * @property-read PDO|null $slavePdo The PDO instance for the currently active slave connection. `null` is
 * returned if no slave connection is available and `$fallbackToMaster` is false.
 * @property-read Transaction|null $transaction The currently active transaction. Null if no active
 * transaction.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Connection extends Component
{
    /**
     * @event \yii\base\Event an event that is triggered after a DB connection is established
     */
    public const EVENT_AFTER_OPEN = 'afterOpen';
    /**
     * @event \yii\base\Event an event that is triggered right before a top-level transaction is started
     */
    public const EVENT_BEGIN_TRANSACTION = 'beginTransaction';
    /**
     * @event \yii\base\Event an event that is triggered right after a top-level transaction is committed
     */
    public const EVENT_COMMIT_TRANSACTION = 'commitTransaction';
    /**
     * @event \yii\base\Event an event that is triggered right after a top-level transaction is rolled back
     */
    public const EVENT_ROLLBACK_TRANSACTION = 'rollbackTransaction';
    /**
     * @var string the Data Source Name, or DSN, contains the information required to connect to the database.
     * Please refer to the [PHP manual](https://www.php.net/manual/en/pdo.construct.php) on
     * the format of the DSN string.
     *
     * For [SQLite](https://www.php.net/manual/en/ref.pdo-sqlite.connection.php) you may use a [path alias](guide:concept-aliases)
     * for specifying the database path, e.g. `sqlite:@app/data/db.sql`.
     *
     * @see charset
     */
    public $dsn;
    /**
     * @var string|null the username for establishing DB connection. Defaults to `null` meaning no username to use.
     */
    public $username;
    /**
     * @var string|null the password for establishing DB connection. Defaults to `null` meaning no password to use.
     */
    public $password;
    /**
     * @var array PDO attributes (name => value) that should be set when calling [[open()]]
     * to establish a DB connection. Please refer to the
     * [PHP manual](https://www.php.net/manual/en/pdo.setattribute.php) for
     * details about available attributes.
     */
    public $attributes;
    /**
     * @var PDO|null the PHP PDO instance associated with this DB connection.
     * This property is mainly managed by [[open()]] and [[close()]] methods.
     * When a DB connection is active, this property will represent a PDO instance;
     * otherwise, it will be null.
     * @see pdoClass
     */
    public $pdo;
    /**
     * @var bool whether to enable schema caching.
     * Note that in order to enable truly schema caching, a valid cache component as specified
     * by [[schemaCache]] must be enabled and [[enableSchemaCache]] must be set true.
     * @see schemaCacheDuration
     * @see schemaCacheExclude
     * @see schemaCache
     */
    public $enable_schema_cache = false;
    /**
     * @var int number of seconds that table metadata can remain valid in cache.
     * Use 0 to indicate that the cached data will never expire.
     * @see enableSchemaCache
     */
    public $schema_cache_duration = 3600;
    /**
     * @var array list of tables whose metadata should NOT be cached. Defaults to empty array.
     * The table names may contain schema prefix, if any. Do not quote the table names.
     * @see enableSchemaCache
     */
    public $schema_cache_exclude = [];
    /**
     * @var CacheInterface|string the cache object or the ID of the cache application component that
     * is used to cache the table metadata.
     * @see enableSchemaCache
     */
    public $schema_cache = 'cache';
    /**
     * @var bool whether to enable query caching.
     * Note that in order to enable query caching, a valid cache component as specified
     * by [[queryCache]] must be enabled and [[enableQueryCache]] must be set true.
     * Also, only the results of the queries enclosed within [[cache()]] will be cached.
     * @see queryCache
     * @see cache()
     * @see noCache()
     */
    public $enable_query_cache = true;
    /**
     * @var int the default number of seconds that query results can remain valid in cache.
     * Defaults to 3600, meaning 3600 seconds, or one hour. Use 0 to indicate that the cached data will never expire.
     * The value of this property will be used when [[cache()]] is called without a cache duration.
     * @see enableQueryCache
     * @see cache()
     */
    public $query_cache_duration = 3600;
    /**
     * @var CacheInterface|string the cache object or the ID of the cache application component
     * that is used for query caching.
     * @see enableQueryCache
     */
    public $query_cache = 'cache';
    /**
     * @var string|null the charset used for database connection. The property is only used
     * for MySQL, PostgreSQL and CUBRID databases. Defaults to null, meaning using default charset
     * as configured by the database.
     *
     * For Oracle Database, the charset must be specified in the [[dsn]], for example for UTF-8 by appending `;charset=UTF-8`
     * to the DSN string.
     *
     * The same applies for if you're using GBK or BIG5 charset with MySQL, then it's highly recommended to
     * specify charset via [[dsn]] like `'mysql:dbname=mydatabase;host=127.0.0.1;charset=GBK;'`.
     */
    public $charset;
    /**
     * @var bool|null whether to turn on prepare emulation. Defaults to false, meaning PDO
     * will use the native prepare support if available. For some databases (such as MySQL),
     * this may need to be set true so that PDO can emulate the prepare support to bypass
     * the buggy native prepare support.
     * The default value is null, which means the PDO ATTR_EMULATE_PREPARES value will not be changed.
     */
    public $emulate_prepare;
    /**
     * @var string the common prefix or suffix for table names. If a table name is given
     * as `{{%TableName}}`, then the percentage character `%` will be replaced with this
     * property value. For example, `{{%post}}` becomes `{{tbl_post}}`.
     */
    public $table_prefix = '';
    /**
     * @var array mapping between PDO driver names and [[Schema]] classes.
     * The keys of the array are PDO driver names while the values are either the corresponding
     * schema class names or configurations. Please refer to [[Yii::createObject()]] for
     * details on how to specify a configuration.
     *
     * This property is mainly used by [[getSchema()]] when fetching the database schema information.
     * You normally do not need to set this property unless you want to use your own
     * [[Schema]] class to support DBMS that is not supported by Yii.
     */
    public $schema_map = [
        'pgsql' => 'yii\db\pgsql\Schema',
        // PostgreSQL
        'mysqli' => 'yii\db\mysql\Schema',
        // MySQL
        'mysql' => 'yii\db\mysql\Schema',
        // MySQL
        'sqlite' => 'yii\db\sqlite\Schema',
        // sqlite 3
        'sqlite2' => 'yii\db\sqlite\Schema',
        // sqlite 2
        'sqlsrv' => 'yii\db\mssql\Schema',
        // newer MSSQL driver on MS Windows hosts
        'oci' => 'yii\db\oci\Schema',
        // Oracle driver
        'mssql' => 'yii\db\mssql\Schema',
        // older MSSQL driver on MS Windows hosts
        'dblib' => 'yii\db\mssql\Schema',
        // dblib drivers on GNU/Linux (and maybe other OSes) hosts
        'cubrid' => 'yii\db\cubrid\Schema',
    ];
    /**
     * @var string|null Custom PDO wrapper class. If not set, it will use [[PDO]] or [[\yii\db\mssql\PDO]] when MSSQL is used.
     * @see pdo
     */
    public $pdo_class;
    /**
     * @var string the class used to create new database [[Command]] objects. If you want to extend the [[Command]] class,
     * you may configure this property to use your extended version of the class.
     * Since version 2.0.14 [[$commandMap]] is used if this property is set to its default value.
     * @see createCommand
     * @since 2.0.7
     * @deprecated since 2.0.14. Use [[$commandMap]] for precise configuration.
     */
    public $command_class = 'yii\db\Command';
    /**
     * @var array mapping between PDO driver names and [[Command]] classes.
     * The keys of the array are PDO driver names while the values are either the corresponding
     * command class names or configurations. Please refer to [[Yii::createObject()]] for
     * details on how to specify a configuration.
     *
     * This property is mainly used by [[createCommand()]] to create new database [[Command]] objects.
     * You normally do not need to set this property unless you want to use your own
     * [[Command]] class or support DBMS that is not supported by Yii.
     * @since 2.0.14
     */
    public $command_map = [
        'pgsql' => 'yii\db\Command',
        // PostgreSQL
        'mysqli' => 'yii\db\Command',
        // MySQL
        'mysql' => 'yii\db\Command',
        // MySQL
        'sqlite' => 'yii\db\sqlite\Command',
        // sqlite 3
        'sqlite2' => 'yii\db\sqlite\Command',
        // sqlite 2
        'sqlsrv' => 'yii\db\Command',
        // newer MSSQL driver on MS Windows hosts
        'oci' => 'yii\db\oci\Command',
        // Oracle driver
        'mssql' => 'yii\db\Command',
        // older MSSQL driver on MS Windows hosts
        'dblib' => 'yii\db\Command',
        // dblib drivers on GNU/Linux (and maybe other OSes) hosts
        'cubrid' => 'yii\db\Command',
    ];
    /**
     * @var bool whether to enable [savepoint](https://en.wikipedia.org/wiki/Savepoint).
     * Note that if the underlying DBMS does not support savepoint, setting this property to be true will have no effect.
     */
    public $enable_savepoint = true;
    /**
     * @var CacheInterface|string|false the cache object or the ID of the cache application component that is used to store
     * the health status of the DB servers specified in [[masters]] and [[slaves]].
     * This is used only when read/write splitting is enabled or [[masters]] is not empty.
     * Set boolean `false` to disabled server status caching.
     * @see openFromPoolSequentially() for details about the failover behavior.
     * @see serverRetryInterval
     */
    public $server_status_cache = 'cache';
    /**
     * @var int the retry interval in seconds for dead servers listed in [[masters]] and [[slaves]].
     * This is used together with [[serverStatusCache]].
     */
    public $server_retry_interval = 600;
    /**
     * @var bool whether to enable read/write splitting by using [[slaves]] to read data.
     * Note that if [[slaves]] is empty, read/write splitting will NOT be enabled no matter what value this property takes.
     */
    public $enable_slaves = true;
    /**
     * @var array list of slave connection configurations. Each configuration is used to create a slave DB connection.
     * When [[enableSlaves]] is true, one of these configurations will be chosen and used to create a DB connection
     * for performing read queries only.
     * @see enableSlaves
     * @see slaveConfig
     */
    public $slaves = [];
    /**
     * @var array the configuration that should be merged with every slave configuration listed in [[slaves]].
     * For example,
     *
     * ```
     * [
     *     'username' => 'slave',
     *     'password' => 'slave',
     *     'attributes' => [
     *         // use a smaller connection timeout
     *         PDO::ATTR_TIMEOUT => 10,
     *     ],
     * ]
     * ```
     */
    public $slave_config = [];
    /**
     * @var array list of master connection configurations. Each configuration is used to create a master DB connection.
     * When [[open()]] is called, one of these configurations will be chosen and used to create a DB connection
     * which will be used by this object.
     * Note that when this property is not empty, the connection setting (e.g. "dsn", "username") of this object will
     * be ignored.
     * @see masterConfig
     * @see shuffleMasters
     */
    public $masters = [];
    /**
     * @var array the configuration that should be merged with every master configuration listed in [[masters]].
     * For example,
     *
     * ```
     * [
     *     'username' => 'master',
     *     'password' => 'master',
     *     'attributes' => [
     *         // use a smaller connection timeout
     *         PDO::ATTR_TIMEOUT => 10,
     *     ],
     * ]
     * ```
     */
    public $master_config = [];
    /**
     * @var bool whether to shuffle [[masters]] before getting one.
     * @since 2.0.11
     * @see masters
     */
    public $shuffle_masters = true;
    /**
     * @var bool whether to enable logging of database queries. Defaults to true.
     * You may want to disable this option in a production environment to gain performance
     * if you do not need the information being logged.
     * @since 2.0.12
     * @see enableProfiling
     */
    public $enable_logging = true;
    /**
     * @var bool whether to enable profiling of opening database connection and database queries. Defaults to true.
     * You may want to disable this option in a production environment to gain performance
     * if you do not need the information being logged.
     * @since 2.0.12
     * @see enableLogging
     */
    public $enable_profiling = true;
    /**
     * @var bool If the database connected via pdo_dblib is SyBase.
     * @since 2.0.38
     */
    public $is_sybase = false;
    /**
     * @var array An array of [[setQueryBuilder()]] calls, holding the passed arguments.
     * Is used to restore a QueryBuilder configuration after the connection close/open cycle.
     *
     * @see restoreQueryBuilderConfiguration()
     */
    private $_query_builder_configurations = [];
    /**
     * @var Transaction|null the currently active transaction
     */
    private ?\yii\db\Transaction $_transaction = null;
    /**
     * @var Schema|null the database schema
     */
    private $_schema;
    /**
     * @var string|null driver name
     */
    private $_driver_name;
    /**
     * @var Connection|false the currently active master connection
     */
    private $_master = false;
    /**
     * @var Connection|false the currently active slave connection
     */
    private $_slave = false;
    /**
     * @var array query cache parameters for the [[cache()]] calls
     */
    private array $_query_cache_info = [];
    /**
     * @var string[]|null quoted table name cache for [[quoteTableName()]] calls
     */
    private ?array $_quoted_table_names = null;
    /**
     * @var string[]|null quoted column name cache for [[quoteColumnName()]] calls
     */
    private ?array $_quoted_column_names = null;
    /**
     * Returns a value indicating whether the DB connection is established.
     * @return bool whether the DB connection is established
     */
    public function get_is_active(): bool
    {
        return $this->pdo !== null;
    }
    /**
     * Uses query cache for the queries performed with the callable.
     *
     * When query caching is enabled ([[enableQueryCache]] is true and [[queryCache]] refers to a valid cache),
     * queries performed within the callable will be cached and their results will be fetched from cache if available.
     * For example,
     *
     * ```
     * // The customer will be fetched from cache if available.
     * // If not, the query will be made against DB and cached for use next time.
     * $customer = $db->cache(function (Connection $db) {
     *     return $db->createCommand('SELECT * FROM customer WHERE id=1')->queryOne();
     * });
     * ```
     *
     * Note that query cache is only meaningful for queries that return results. For queries performed with
     * [[Command::execute()]], query cache will not be used.
     *
     * @param callable $callable a PHP callable that contains DB queries which will make use of query cache.
     * The signature of the callable is `function (Connection $db)`.
     * @param int|null $duration the number of seconds that query results can remain valid in the cache. If this is
     * not set, the value of [[queryCacheDuration]] will be used instead.
     * Use 0 to indicate that the cached data will never expire.
     * @param \yii\caching\Dependency|null $dependency the cache dependency associated with the cached query results.
     * @return mixed the return result of the callable
     * @throws \Throwable if there is any exception during query
     * @see enableQueryCache
     * @see queryCache
     * @see noCache()
     */
    public function cache(callable $callable, $duration = null, $dependency = null)
    {
        $this->_query_cache_info[] = [$duration ?? $this->query_cache_duration, $dependency];
        try {
            $result = call_user_func($callable, $this);
            array_pop($this->_query_cache_info);
            return $result;
        } catch (\Exception|\Throwable $e) {
            array_pop($this->_query_cache_info);
            throw $e;
        }
    }
    /**
     * Disables query cache temporarily.
     *
     * Queries performed within the callable will not use query cache at all. For example,
     *
     * ```
     * $db->cache(function (Connection $db) {
     *
     *     // ... queries that use query cache ...
     *
     *     return $db->noCache(function (Connection $db) {
     *         // this query will not use query cache
     *         return $db->createCommand('SELECT * FROM customer WHERE id=1')->queryOne();
     *     });
     * });
     * ```
     *
     * @param callable $callable a PHP callable that contains DB queries which should not use query cache.
     * The signature of the callable is `function (Connection $db)`.
     * @return mixed the return result of the callable
     * @throws \Throwable if there is any exception during query
     * @see enableQueryCache
     * @see queryCache
     * @see cache()
     */
    public function no_cache(callable $callable)
    {
        $this->_query_cache_info[] = false;
        try {
            $result = call_user_func($callable, $this);
            array_pop($this->_query_cache_info);
            return $result;
        } catch (\Exception|\Throwable $e) {
            array_pop($this->_query_cache_info);
            throw $e;
        }
    }
    /**
     * Returns the current query cache information.
     * This method is used internally by [[Command]].
     * @param int|null $duration the preferred caching duration. If null, it will be ignored.
     * @param \yii\caching\Dependency|null $dependency the preferred caching dependency. If null, it will be ignored.
     * @return array|null the current query cache information, or null if query cache is not enabled.
     * @internal
     */
    public function get_query_cache_info($duration, $dependency): ?array
    {
        if (!$this->enable_query_cache) {
            return null;
        }
        $info = end($this->_query_cache_info);
        if (is_array($info)) {
            if ($duration === null) {
                $duration = $info[0];
            }
            if ($dependency === null) {
                $dependency = $info[1];
            }
        }
        if ($duration === 0 || $duration > 0) {
            if (is_string($this->query_cache) && Yii::$app) {
                $cache = Yii::$app->get($this->query_cache, false);
            } else {
                $cache = $this->query_cache;
            }
            if ($cache instanceof Cache_Interface) {
                return [$cache, $duration, $dependency];
            }
        }
        return null;
    }
    /**
     * Establishes a DB connection.
     * It does nothing if a DB connection has already been established.
     * @throws Exception if connection fails
     */
    public function open(): void
    {
        if ($this->pdo !== null) {
            return;
        }
        if (!empty($this->masters)) {
            $db = $this->get_master();
            if ($db !== null) {
                $this->pdo = $db->pdo;
                return;
            }
            throw new Invalid_Config_Exception('None of the master DB servers is available.');
        }
        if (empty($this->dsn)) {
            throw new Invalid_Config_Exception('Connection::dsn cannot be empty.');
        }
        $token = 'Opening DB connection: ' . $this->dsn;
        $enable_profiling = $this->enable_profiling;
        try {
            if ($this->enable_logging) {
                Yii::info($token, __METHOD__);
            }
            if ($enable_profiling) {
                Yii::begin_profile($token, __METHOD__);
            }
            $this->pdo = $this->create_pdo_instance();
            $this->init_connection();
            if ($enable_profiling) {
                Yii::end_profile($token, __METHOD__);
            }
        } catch (\PDOException $e) {
            if ($enable_profiling) {
                Yii::end_profile($token, __METHOD__);
            }
            throw new Exception($e->get_message(), $e->error_info, $e->get_code(), $e);
        }
    }
    /**
     * Closes the currently active DB connection.
     * It does nothing if the connection is already closed.
     */
    public function close(): void
    {
        if ($this->_master) {
            if ($this->pdo === $this->_master->pdo) {
                $this->pdo = null;
            }
            $this->_master->close();
            $this->_master = false;
        }
        if ($this->pdo !== null) {
            Yii::debug('Closing DB connection: ' . $this->dsn, __METHOD__);
            $this->pdo = null;
        }
        if ($this->_slave) {
            $this->_slave->close();
            $this->_slave = false;
        }
        $this->_schema = null;
        $this->_transaction = null;
        $this->_driver_name = null;
        $this->_query_cache_info = [];
        $this->_quoted_table_names = null;
        $this->_quoted_column_names = null;
    }
    /**
     * Creates the PDO instance.
     * This method is called by [[open]] to establish a DB connection.
     * The default implementation will create a PHP PDO instance.
     * You may override this method if the default PDO needs to be adapted for certain DBMS.
     * @return PDO the pdo instance
     */
    protected function create_pdo_instance()
    {
        $pdo_class = $this->pdo_class;
        if ($pdo_class === null) {
            $driver = null;
            if ($this->_driver_name !== null) {
                $driver = $this->_driver_name;
            } elseif (($pos = strpos($this->dsn, ':')) !== false) {
                $driver = strtolower(substr($this->dsn, 0, $pos));
            }
            switch ($driver) {
                case 'mssql':
                    $pdo_class = 'yii\db\mssql\PDO';
                    break;
                case 'dblib':
                    $pdo_class = 'yii\db\mssql\DBLibPDO';
                    break;
                case 'sqlsrv':
                    $pdo_class = 'yii\db\mssql\SqlsrvPDO';
                    break;
                default:
                    $pdo_class = 'PDO';
            }
        }
        $dsn = $this->dsn;
        if (strncmp('sqlite:@', $dsn, 8) === 0) {
            $dsn = 'sqlite:' . Yii::get_alias(substr($dsn, 7));
        }
        return new $pdo_class($dsn, $this->username, $this->password, $this->attributes);
    }
    /**
     * Initializes the DB connection.
     * This method is invoked right after the DB connection is established.
     * The default implementation turns on `PDO::ATTR_EMULATE_PREPARES`
     * if [[emulatePrepare]] is true, and sets the database [[charset]] if it is not empty.
     * It then triggers an [[EVENT_AFTER_OPEN]] event.
     */
    protected function init_connection()
    {
        $this->pdo->set_attribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($this->emulate_prepare !== null && constant('PDO::ATTR_EMULATE_PREPARES')) {
            if ($this->driver_name !== 'sqlsrv') {
                $this->pdo->set_attribute(PDO::ATTR_EMULATE_PREPARES, $this->emulate_prepare);
            }
        }
        if (PHP_VERSION_ID >= 80100 && $this->get_driver_name() === 'sqlite') {
            $this->pdo->set_attribute(PDO::ATTR_STRINGIFY_FETCHES, true);
        }
        if (!$this->is_sybase && in_array($this->get_driver_name(), ['mssql', 'dblib'], true)) {
            $this->pdo->exec('SET ANSI_NULL_DFLT_ON ON');
        }
        if ($this->charset !== null && in_array($this->get_driver_name(), ['pgsql', 'mysql', 'mysqli', 'cubrid'], true)) {
            $this->pdo->exec('SET NAMES ' . $this->pdo->quote($this->charset));
        }
        $this->trigger(self::EVENT_AFTER_OPEN);
    }
    /**
     * Creates a command for execution.
     * @param string|null $sql the SQL statement to be executed
     * @param array $params the parameters to be bound to the SQL statement
     * @return Command the DB command
     */
    public function create_command($sql = null, $params = [])
    {
        $driver = $this->get_driver_name();
        $config = ['class' => 'yii\db\Command'];
        if ($this->command_class !== $config['class']) {
            $config['class'] = $this->command_class;
        } elseif (isset($this->command_map[$driver])) {
            $config = !is_array($this->command_map[$driver]) ? ['class' => $this->command_map[$driver]] : $this->command_map[$driver];
        }
        $config['db'] = $this;
        $config['sql'] = $sql;
        /** @var Command $command */
        $command = Yii::create_object($config);
        return $command->bind_values($params);
    }
    /**
     * Returns the currently active transaction.
     * @return Transaction|null the currently active transaction. Null if no active transaction.
     */
    public function get_transaction()
    {
        return $this->_transaction && $this->_transaction->get_is_active() ? $this->_transaction : null;
    }
    /**
     * Starts a transaction.
     * @param string|null $isolationLevel The isolation level to use for this transaction.
     * See [[Transaction::begin()]] for details.
     * @return Transaction the transaction initiated
     */
    public function begin_transaction($isolation_level = null)
    {
        $this->open();
        if (($transaction = $this->get_transaction()) === null) {
            $transaction = $this->_transaction = new Transaction(['db' => $this]);
        }
        $transaction->begin($isolation_level);
        return $transaction;
    }
    /**
     * Executes callback provided in a transaction.
     *
     * @param callable $callback a valid PHP callback that performs the job. Accepts connection instance as parameter.
     * @param string|null $isolationLevel The isolation level to use for this transaction.
     * See [[Transaction::begin()]] for details.
     * @throws \Throwable if there is any exception during query. In this case the transaction will be rolled back.
     * @return mixed result of callback function
     */
    public function transaction(callable $callback, $isolation_level = null)
    {
        $transaction = $this->begin_transaction($isolation_level);
        $level = $transaction->level;
        try {
            $result = call_user_func($callback, $this);
            if ($transaction->is_active && $transaction->level === $level) {
                $transaction->commit();
            }
        } catch (\Exception|\Throwable $e) {
            $this->rollback_transaction_on_level($transaction, $level);
            throw $e;
        }
        return $result;
    }
    /**
     * Rolls back given [[Transaction]] object if it's still active and level match.
     * In some cases rollback can fail, so this method is fail safe. Exception thrown
     * from rollback will be caught and just logged with [[\Yii::error()]].
     * @param Transaction $transaction Transaction object given from [[beginTransaction()]].
     * @param int $level Transaction level just after [[beginTransaction()]] call.
     */
    private function rollback_transaction_on_level($transaction, $level): void
    {
        if ($transaction->is_active && $transaction->level === $level) {
            // https://github.com/yiisoft/yii2/pull/13347
            try {
                $transaction->roll_back();
            } catch (\Exception $e) {
                \Yii::error($e, __METHOD__);
                // hide this exception to be able to continue throwing original exception outside
            }
        }
    }
    /**
     * Returns the schema information for the database opened by this connection.
     * @return Schema the schema information for the database opened by this connection.
     * @throws NotSupportedException if there is no support for the current driver type
     */
    public function get_schema()
    {
        if ($this->_schema !== null) {
            return $this->_schema;
        }
        $driver = $this->get_driver_name();
        if (isset($this->schema_map[$driver])) {
            $config = !is_array($this->schema_map[$driver]) ? ['class' => $this->schema_map[$driver]] : $this->schema_map[$driver];
            $config['db'] = $this;
            $this->_schema = Yii::create_object($config);
            $this->restore_query_builder_configuration();
            return $this->_schema;
        }
        throw new Not_Supported_Exception("Connection does not support reading schema information for '{$driver}' DBMS.");
    }
    /**
     * Returns the query builder for the current DB connection.
     * @return QueryBuilder the query builder for the current DB connection.
     */
    public function get_query_builder()
    {
        return $this->get_schema()->get_query_builder();
    }
    /**
     * Can be used to set [[QueryBuilder]] configuration via Connection configuration array.
     *
     * @param array $value the [[QueryBuilder]] properties to be configured.
     * @since 2.0.14
     */
    public function set_query_builder($value): void
    {
        Yii::configure($this->get_query_builder(), $value);
        $this->_query_builder_configurations[] = $value;
    }
    /**
     * Restores custom QueryBuilder configuration after the connection close/open cycle
     */
    private function restore_query_builder_configuration(): void
    {
        if ($this->_query_builder_configurations === []) {
            return;
        }
        $query_builder_configurations = $this->_query_builder_configurations;
        $this->_query_builder_configurations = [];
        foreach ($query_builder_configurations as $query_builder_configuration) {
            $this->set_query_builder($query_builder_configuration);
        }
    }
    /**
     * Obtains the schema information for the named table.
     * @param string $name table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     * @return TableSchema|null table schema information. Null if the named table does not exist.
     */
    public function get_table_schema($name, $refresh = false)
    {
        return $this->get_schema()->get_table_schema($name, $refresh);
    }
    /**
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @see https://www.php.net/manual/en/pdo.lastinsertid.php
     */
    public function get_last_insert_id($sequence_name = '')
    {
        return $this->get_schema()->get_last_insert_id($sequence_name);
    }
    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $value string to be quoted
     * @return string the properly quoted string
     * @see https://www.php.net/manual/en/pdo.quote.php
     */
    public function quote_value($value)
    {
        return $this->get_schema()->quote_value($value);
    }
    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quote_table_name($name)
    {
        return $this->_quoted_table_names[$name] ?? $this->_quoted_table_names[$name] = $this->get_schema()->quote_table_name($name);
    }
    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains special characters including '(', '[[' and '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quote_column_name($name)
    {
        return $this->_quoted_column_names[$name] ?? $this->_quoted_column_names[$name] = $this->get_schema()->quote_column_name($name);
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
    public function quote_sql($sql): ?string
    {
        return preg_replace_callback('/(\{\{(%?[\w\-\. ]+%?)\}\}|\[\[([\w\-\. ]+)\]\])/', function ($matches) {
            if (isset($matches[3])) {
                return $this->quote_column_name($matches[3]);
            }
            return str_replace('%', $this->table_prefix, $this->quote_table_name($matches[2]));
        }, $sql);
    }
    /**
     * Returns the name of the DB driver. Based on the the current [[dsn]], in case it was not set explicitly
     * by an end user.
     * @return string|null name of the DB driver
     */
    public function get_driver_name()
    {
        if ($this->_driver_name === null) {
            if (($pos = strpos((string) $this->dsn, ':')) !== false) {
                $this->_driver_name = strtolower(substr($this->dsn, 0, $pos));
            } else {
                $this->_driver_name = strtolower($this->get_slave_pdo(true)->get_attribute(PDO::ATTR_DRIVER_NAME));
            }
        }
        return $this->_driver_name;
    }
    /**
     * Changes the current driver name.
     * @param string $driverName name of the DB driver
     */
    public function set_driver_name($driver_name): void
    {
        $this->_driver_name = strtolower($driver_name);
    }
    /**
     * Returns a server version as a string comparable by [[\version_compare()]].
     * @return string server version as a string.
     * @since 2.0.14
     */
    public function get_server_version()
    {
        return $this->get_schema()->get_server_version();
    }
    /**
     * Returns the PDO instance for the currently active slave connection.
     * When [[enableSlaves]] is true, one of the slaves will be used for read queries, and its PDO instance
     * will be returned by this method.
     * @param bool $fallbackToMaster whether to return a master PDO in case none of the slave connections is available.
     * @return PDO|null the PDO instance for the currently active slave connection. `null` is returned if no slave connection
     * is available and `$fallbackToMaster` is false.
     */
    public function get_slave_pdo($fallback_to_master = true)
    {
        $db = $this->get_slave(false);
        if ($db === null) {
            return $fallback_to_master ? $this->get_master_pdo() : null;
        }
        return $db->pdo;
    }
    /**
     * Returns the PDO instance for the currently active master connection.
     * This method will open the master DB connection and then return [[pdo]].
     * @return PDO the PDO instance for the currently active master connection.
     */
    public function get_master_pdo()
    {
        $this->open();
        return $this->pdo;
    }
    /**
     * Returns the currently active slave connection.
     * If this method is called for the first time, it will try to open a slave connection when [[enableSlaves]] is true.
     * @param bool $fallbackToMaster whether to return a master connection in case there is no slave connection available.
     * @return Connection|null the currently active slave connection. `null` is returned if there is no slave available and
     * `$fallbackToMaster` is false.
     */
    public function get_slave($fallback_to_master = true)
    {
        if (!$this->enable_slaves) {
            return $fallback_to_master ? $this : null;
        }
        if ($this->_slave === false) {
            $this->_slave = $this->open_from_pool($this->slaves, $this->slave_config);
        }
        return $this->_slave === null && $fallback_to_master ? $this : $this->_slave;
    }
    /**
     * Returns the currently active master connection.
     * If this method is called for the first time, it will try to open a master connection.
     * @return Connection|null the currently active master connection. `null` is returned if there is no master available.
     * @since 2.0.11
     */
    public function get_master()
    {
        if ($this->_master === false) {
            $this->_master = $this->shuffle_masters ? $this->open_from_pool($this->masters, $this->master_config) : $this->open_from_pool_sequentially($this->masters, $this->master_config);
        }
        return $this->_master;
    }
    /**
     * Executes the provided callback by using the master connection.
     *
     * This method is provided so that you can temporarily force using the master connection to perform
     * DB operations even if they are read queries. For example,
     *
     * ```
     * $result = $db->useMaster(function ($db) {
     *     return $db->createCommand('SELECT * FROM user LIMIT 1')->queryOne();
     * });
     * ```
     *
     * @param callable $callback a PHP callable to be executed by this method. Its signature is
     * `function (Connection $db)`. Its return value will be returned by this method.
     * @return mixed the return value of the callback
     * @throws \Throwable if there is any exception thrown from the callback
     */
    public function use_master(callable $callback)
    {
        if ($this->enable_slaves) {
            $this->enable_slaves = false;
            try {
                $result = call_user_func($callback, $this);
            } catch (\Exception|\Throwable $e) {
                $this->enable_slaves = true;
                throw $e;
            }
            // TODO: use "finally" keyword when miminum required PHP version is >= 5.5
            $this->enable_slaves = true;
        } else {
            $result = call_user_func($callback, $this);
        }
        return $result;
    }
    /**
     * Opens the connection to a server in the pool.
     *
     * This method implements load balancing and failover among the given list of the servers.
     * Connections will be tried in random order.
     * For details about the failover behavior, see [[openFromPoolSequentially]].
     *
     * @param array $pool the list of connection configurations in the server pool
     * @param array $sharedConfig the configuration common to those given in `$pool`.
     * @return Connection|null the opened DB connection, or `null` if no server is available
     * @throws InvalidConfigException if a configuration does not specify "dsn"
     * @see openFromPoolSequentially
     */
    protected function open_from_pool(array $pool, array $shared_config)
    {
        shuffle($pool);
        return $this->open_from_pool_sequentially($pool, $shared_config);
    }
    /**
     * Opens the connection to a server in the pool.
     *
     * This method implements failover among the given list of servers.
     * Connections will be tried in sequential order. The first successful connection will return.
     *
     * If [[serverStatusCache]] is configured, this method will cache information about
     * unreachable servers and does not try to connect to these for the time configured in [[serverRetryInterval]].
     * This helps to keep the application stable when some servers are unavailable. Avoiding
     * connection attempts to unavailable servers saves time when the connection attempts fail due to timeout.
     *
     * If none of the servers are available the status cache is ignored and connection attempts are made to all
     * servers (Since version 2.0.35). This is to avoid downtime when all servers are unavailable for a short time.
     * After a successful connection attempt the server is marked as available again.
     *
     * @param array $pool the list of connection configurations in the server pool
     * @param array $sharedConfig the configuration common to those given in `$pool`.
     * @return Connection|null the opened DB connection, or `null` if no server is available
     * @throws InvalidConfigException if a configuration does not specify "dsn"
     * @since 2.0.11
     * @see openFromPool
     * @see serverStatusCache
     */
    protected function open_from_pool_sequentially(array $pool, array $shared_config)
    {
        if (empty($pool)) {
            return null;
        }
        if (!isset($shared_config['class'])) {
            $shared_config['class'] = get_class($this);
        }
        $cache = is_string($this->server_status_cache) ? Yii::$app->get($this->server_status_cache, false) : $this->server_status_cache;
        foreach ($pool as $i => $config) {
            $pool[$i] = $config = array_merge($shared_config, $config);
            if (empty($config['dsn'])) {
                throw new Invalid_Config_Exception('The "dsn" option must be specified.');
            }
            $key = [__METHOD__, $config['dsn']];
            if ($cache instanceof Cache_Interface && $cache->get($key)) {
                // should not try this dead server now
                continue;
            }
            /** @var self $db */
            $db = Yii::create_object($config);
            try {
                $db->open();
                return $db;
            } catch (\Exception $e) {
                Yii::warning("Connection ({$config['dsn']}) failed: " . $e->get_message(), __METHOD__);
                if ($cache instanceof Cache_Interface) {
                    // mark this server as dead and only retry it after the specified interval
                    $cache->set($key, 1, $this->server_retry_interval);
                }
                // exclude server from retry below
                unset($pool[$i]);
            }
        }
        if ($cache instanceof Cache_Interface) {
            // if server status cache is enabled and no server is available
            // ignore the cache and try to connect anyway
            // $pool now only contains servers we did not already try in the loop above
            foreach ($pool as $config) {
                /** @var self $db */
                $db = Yii::create_object($config);
                try {
                    $db->open();
                } catch (\Exception $e) {
                    Yii::warning("Connection ({$config['dsn']}) failed: " . $e->get_message(), __METHOD__);
                    continue;
                }
                // mark this server as available again after successful connection
                $cache->delete([__METHOD__, $config['dsn']]);
                return $db;
            }
        }
        return null;
    }
    /**
     * Close the connection before serializing.
     * @return array
     */
    public function __sleep()
    {
        $fields = (array) $this;
        unset($fields['pdo']);
        unset($fields["\x00" . self::class . "\x00" . '_master']);
        unset($fields["\x00" . self::class . "\x00" . '_slave']);
        unset($fields["\x00" . self::class . "\x00" . '_transaction']);
        unset($fields["\x00" . self::class . "\x00" . '_schema']);
        return array_keys($fields);
    }
    /**
     * Reset the connection after cloning.
     */
    public function __clone()
    {
        parent::__clone();
        $this->_master = false;
        $this->_slave = false;
        $this->_schema = null;
        $this->_transaction = null;
        if (strncmp($this->dsn, 'sqlite::memory:', 15) !== 0) {
            // reset PDO connection, unless its sqlite in-memory, which can only have one connection
            $this->pdo = null;
        }
    }
}