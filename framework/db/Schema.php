<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use Yii;
use yii\base\Base_Object;
use yii\base\Invalid_Call_Exception;
use yii\base\Invalid_Config_Exception;
use yii\base\Not_Supported_Exception;
use yii\caching\Cache;
use yii\caching\Cache_Interface;
use yii\caching\Tag_Dependency;
/**
 * Schema is the base class for concrete DBMS-specific schema classes.
 *
 * Schema represents the database schema information that is DBMS specific.
 *
 * @property-read string $lastInsertID The row ID of the last row inserted, or the last value retrieved from
 * the sequence object.
 * @property-read QueryBuilder $queryBuilder The query builder for this connection.
 * @property-read string[] $schemaNames All schema names in the database, except system schemas.
 * @property-read string $serverVersion Server version as a string.
 * @property-read string[] $tableNames All table names in the database.
 * @property-read TableSchema[] $tableSchemas The metadata for all tables in the database. Each array element
 * is an instance of [[TableSchema]] or its child class.
 * @property-write string $transactionIsolationLevel The transaction isolation level to use for this
 * transaction. This can be one of [[Transaction::READ_UNCOMMITTED]], [[Transaction::READ_COMMITTED]],
 * [[Transaction::REPEATABLE_READ]] and [[Transaction::SERIALIZABLE]] but also a string containing DBMS specific
 * syntax to be used after `SET TRANSACTION ISOLATION LEVEL`.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0
 *
 * @template T of ColumnSchema = ColumnSchema
 */
abstract class Schema extends Base_Object
{
    // The following are the supported abstract column data types.
    public const TYPE_PK = 'pk';
    public const TYPE_UPK = 'upk';
    public const TYPE_BIGPK = 'bigpk';
    public const TYPE_UBIGPK = 'ubigpk';
    public const TYPE_CHAR = 'char';
    public const TYPE_STRING = 'string';
    public const TYPE_TEXT = 'text';
    public const TYPE_TINYINT = 'tinyint';
    public const TYPE_SMALLINT = 'smallint';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_BIGINT = 'bigint';
    public const TYPE_FLOAT = 'float';
    public const TYPE_DOUBLE = 'double';
    public const TYPE_DECIMAL = 'decimal';
    public const TYPE_DATETIME = 'datetime';
    public const TYPE_TIMESTAMP = 'timestamp';
    public const TYPE_TIME = 'time';
    public const TYPE_DATE = 'date';
    public const TYPE_BINARY = 'binary';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_MONEY = 'money';
    public const TYPE_JSON = 'json';
    /**
     * Schema cache version, to detect incompatibilities in cached values when the
     * data format of the cache changes.
     */
    public const SCHEMA_CACHE_VERSION = 1;
    /**
     * @var Connection the database connection
     */
    public $db;
    /**
     * @var string the default schema name used for the current session.
     */
    public $default_schema;
    /**
     * @var array map of DB errors and corresponding exceptions
     * If left part is found in DB error message exception class from the right part is used.
     */
    public $exception_map = ['SQLSTATE[23' => 'yii\db\IntegrityException'];
    /**
     * @var class-string<T>|array{class?: class-string<T>, __class?: class-string<T>, ...} column schema class or class config
     * @since 2.0.11
     */
    public $column_schema_class = 'yii\db\ColumnSchema';
    /**
     * @var string|string[] character used to quote schema, table, etc. names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     * @since 2.0.14
     */
    protected $table_quote_character = "'";
    /**
     * @var string|string[] character used to quote column names.
     * An array of 2 characters can be used in case starting and ending characters are different.
     * @since 2.0.14
     */
    protected $column_quote_character = '"';
    /**
     * @var array list of ALL schema names in the database, except system schemas
     */
    private $_schema_names;
    /**
     * @var array list of ALL table names in the database
     */
    private array $_table_names = [];
    /**
     * @var array list of loaded table metadata (table name => metadata type => metadata).
     */
    private array $_table_metadata = [];
    /**
     * @var QueryBuilder the query builder for this database
     */
    private $_builder;
    /**
     * @var string server version as a string.
     */
    private $_server_version;
    /**
     * Resolves the table name and schema name (if any).
     * @param string $name the table name
     * @return TableSchema [[TableSchema]] with resolved table, schema, etc. names.
     * @throws NotSupportedException if this method is not supported by the DBMS.
     * @since 2.0.13
     */
    protected function resolve_table_name($name)
    {
        throw new Not_Supported_Exception(get_class($this) . ' does not support resolving table names.');
    }
    /**
     * Returns all schema names in the database, including the default one but not system schemas.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     * @return array all schema names in the database, except system schemas.
     * @throws NotSupportedException if this method is not supported by the DBMS.
     * @since 2.0.4
     */
    protected function find_schema_names()
    {
        throw new Not_Supported_Exception(get_class($this) . ' does not support fetching all schema names.');
    }
    /**
     * Returns all table names in the database.
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema.
     * @return array all table names in the database. The names have NO schema name prefix.
     * @throws NotSupportedException if this method is not supported by the DBMS.
     */
    protected function find_table_names($schema = '')
    {
        throw new Not_Supported_Exception(get_class($this) . ' does not support fetching all table names.');
    }
    /**
     * Loads the metadata for the specified table.
     * @param string $name table name
     * @return TableSchema|null DBMS-dependent table metadata, `null` if the table does not exist.
     */
    abstract protected function load_table_schema($name);
    /**
     * Creates a column schema for the database.
     * This method may be overridden by child classes to create a DBMS-specific column schema.
     * @return T column schema instance.
     * @throws InvalidConfigException if a column schema class cannot be created.
     */
    protected function create_column_schema()
    {
        return Yii::create_object($this->column_schema_class);
    }
    /**
     * Obtains the metadata for the named table.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param bool $refresh whether to reload the table schema even if it is found in the cache.
     * @return TableSchema|null table metadata. `null` if the named table does not exist.
     */
    public function get_table_schema($name, $refresh = false)
    {
        return $this->get_table_metadata($name, 'schema', $refresh);
    }
    /**
     * Returns the metadata for all tables in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * @param bool $refresh whether to fetch the latest available table schemas. If this is `false`,
     * cached data may be returned if available.
     * @return TableSchema[] the metadata for all tables in the database.
     * Each array element is an instance of [[TableSchema]] or its child class.
     */
    public function get_table_schemas($schema = '', $refresh = false)
    {
        return $this->get_schema_metadata($schema, 'schema', $refresh);
    }
    /**
     * Returns all schema names in the database, except system schemas.
     * @param bool $refresh whether to fetch the latest available schema names. If this is false,
     * schema names fetched previously (if available) will be returned.
     * @return string[] all schema names in the database, except system schemas.
     * @since 2.0.4
     */
    public function get_schema_names($refresh = false)
    {
        if ($this->_schema_names === null || $refresh) {
            $this->_schema_names = $this->find_schema_names();
        }
        return $this->_schema_names;
    }
    /**
     * Returns all table names in the database.
     * @param string $schema the schema of the tables. Defaults to empty string, meaning the current or default schema name.
     * If not empty, the returned table names will be prefixed with the schema name.
     * @param bool $refresh whether to fetch the latest available table names. If this is false,
     * table names fetched previously (if available) will be returned.
     * @return string[] all table names in the database.
     */
    public function get_table_names($schema = '', $refresh = false)
    {
        if (!isset($this->_table_names[$schema]) || $refresh) {
            $this->_table_names[$schema] = $this->find_table_names($schema);
        }
        return $this->_table_names[$schema];
    }
    /**
     * @return QueryBuilder the query builder for this connection.
     */
    public function get_query_builder()
    {
        if ($this->_builder === null) {
            $this->_builder = $this->create_query_builder();
        }
        return $this->_builder;
    }
    /**
     * Determines the PDO type for the given PHP data value.
     * @param mixed $data the data whose PDO type is to be determined
     * @return int the PDO type
     * @see https://www.php.net/manual/en/pdo.constants.php
     */
    public function get_pdo_type($data)
    {
        static $type_map = [
            // php type => PDO type
            'boolean' => \PDO::PARAM_BOOL,
            'integer' => \PDO::PARAM_INT,
            'string' => \PDO::PARAM_STR,
            'resource' => \PDO::PARAM_LOB,
            'NULL' => \PDO::PARAM_NULL,
        ];
        $type = gettype($data);
        return $type_map[$type] ?? \PDO::PARAM_STR;
    }
    /**
     * Refreshes the schema.
     * This method cleans up all cached table schemas so that they can be re-created later
     * to reflect the database schema change.
     */
    public function refresh(): void
    {
        /** @var CacheInterface $cache */
        $cache = is_string($this->db->schema_cache) ? Yii::$app->get($this->db->schema_cache, false) : $this->db->schema_cache;
        if ($this->db->enable_schema_cache && $cache instanceof Cache_Interface) {
            Tag_Dependency::invalidate($cache, $this->get_cache_tag());
        }
        $this->_table_names = [];
        $this->_table_metadata = [];
    }
    /**
     * Refreshes the particular table schema.
     * This method cleans up cached table schema so that it can be re-created later
     * to reflect the database schema change.
     * @param string $name table name.
     * @since 2.0.6
     */
    public function refresh_table_schema($name): void
    {
        $raw_name = $this->get_raw_table_name($name);
        unset($this->_table_metadata[$raw_name]);
        $this->_table_names = [];
        /** @var CacheInterface $cache */
        $cache = is_string($this->db->schema_cache) ? Yii::$app->get($this->db->schema_cache, false) : $this->db->schema_cache;
        if ($this->db->enable_schema_cache && $cache instanceof Cache_Interface) {
            $cache->delete($this->get_cache_key($raw_name));
        }
    }
    /**
     * Creates a query builder for the database.
     * This method may be overridden by child classes to create a DBMS-specific query builder.
     * @return QueryBuilder query builder instance
     */
    public function create_query_builder()
    {
        return Yii::create_object(Query_Builder::class_name(), [$this->db]);
    }
    /**
     * Create a column schema builder instance giving the type and value precision.
     *
     * This method may be overridden by child classes to create a DBMS-specific column schema builder.
     *
     * @param string $type type of the column. See [[ColumnSchemaBuilder::$type]].
     * @param int|string|array|null $length length or precision of the column. See [[ColumnSchemaBuilder::$length]].
     * @return ColumnSchemaBuilder column schema builder instance
     * @since 2.0.6
     */
    public function create_column_schema_builder($type, $length = null)
    {
        return Yii::create_object(Column_Schema_Builder::class_name(), [$type, $length]);
    }
    /**
     * Returns all unique indexes for the given table.
     *
     * Each array element is of the following structure:
     *
     * ```
     * [
     *  'IndexName1' => ['col1' [, ...]],
     *  'IndexName2' => ['col2' [, ...]],
     * ]
     * ```
     *
     * This method should be overridden by child classes in order to support this feature
     * because the default implementation simply throws an exception
     * @param TableSchema $table the table metadata
     * @return array all unique indexes for the given table.
     * @throws NotSupportedException if this method is called
     */
    public function find_unique_indexes($table)
    {
        throw new Not_Supported_Exception(get_class($this) . ' does not support getting unique indexes information.');
    }
    /**
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @throws InvalidCallException if the DB connection is not active
     * @see https://www.php.net/manual/en/function.PDO-lastInsertId.php
     */
    public function get_last_insert_id($sequence_name = '')
    {
        if ($this->db->is_active) {
            return $this->db->pdo->last_insert_id($sequence_name === '' ? null : $this->quote_table_name($sequence_name));
        }
        throw new Invalid_Call_Exception('DB Connection is not active.');
    }
    /**
     * @return bool whether this DBMS supports [savepoint](https://en.wikipedia.org/wiki/Savepoint).
     */
    public function supports_savepoint()
    {
        return $this->db->enable_savepoint;
    }
    /**
     * Creates a new savepoint.
     * @param string $name the savepoint name
     */
    public function create_savepoint($name): void
    {
        $this->db->create_command("SAVEPOINT {$name}")->execute();
    }
    /**
     * Releases an existing savepoint.
     * @param string $name the savepoint name
     */
    public function release_savepoint($name): void
    {
        $this->db->create_command("RELEASE SAVEPOINT {$name}")->execute();
    }
    /**
     * Rolls back to a previously created savepoint.
     * @param string $name the savepoint name
     */
    public function roll_back_savepoint($name): void
    {
        $this->db->create_command("ROLLBACK TO SAVEPOINT {$name}")->execute();
    }
    /**
     * Sets the isolation level of the current transaction.
     * @param string $level The transaction isolation level to use for this transaction.
     * This can be one of [[Transaction::READ_UNCOMMITTED]], [[Transaction::READ_COMMITTED]], [[Transaction::REPEATABLE_READ]]
     * and [[Transaction::SERIALIZABLE]] but also a string containing DBMS specific syntax to be used
     * after `SET TRANSACTION ISOLATION LEVEL`.
     * @see https://en.wikipedia.org/wiki/Isolation_%28database_systems%29#Isolation_levels
     */
    public function set_transaction_isolation_level($level): void
    {
        $this->db->create_command("SET TRANSACTION ISOLATION LEVEL {$level}")->execute();
    }
    /**
     * Executes the INSERT command, returning primary key values.
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column data (name => value) to be inserted into the table.
     * @return array|false primary key values or false if the command fails
     * @since 2.0.4
     */
    public function insert($table, array $columns)
    {
        $command = $this->db->create_command()->insert($table, $columns);
        if (!$command->execute()) {
            return false;
        }
        $table_schema = $this->get_table_schema($table);
        $result = [];
        foreach ($table_schema->primary_key as $name) {
            if ($table_schema->columns[$name]->auto_increment) {
                $result[$name] = $this->get_last_insert_id($table_schema->sequence_name);
                break;
            }
            $result[$name] = $columns[$name] ?? $table_schema->columns[$name]->default_value;
        }
        return $result;
    }
    /**
     * Quotes a string value for use in a query.
     * Note that if the parameter is not a string, it will be returned without change.
     * @param string $str string to be quoted
     * @return string the properly quoted string
     * @see https://www.php.net/manual/en/function.PDO-quote.php
     */
    public function quote_value($str)
    {
        if (!is_string($str)) {
            return $str;
        }
        if (mb_stripos((string) $this->db->dsn, 'odbc:') === false && ($value = $this->db->get_slave_pdo(true)->quote($str)) !== false) {
            return $value;
        }
        // the driver doesn't support quote (e.g. oci)
        return "'" . addcslashes(str_replace("'", "''", $str), "\x00\n\r\\\x1a") . "'";
    }
    /**
     * Quotes a table name for use in a query.
     * If the table name contains schema prefix, the prefix will also be properly quoted.
     * If the table name is already quoted or contains '(' or '{{',
     * then this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     * @see quoteSimpleTableName()
     */
    public function quote_table_name($name)
    {
        if (strncmp($name, '(', 1) === 0 && strpos($name, ')') === strlen($name) - 1) {
            return $name;
        }
        if (strpos($name, '{{') !== false) {
            return $name;
        }
        if (strpos($name, '.') === false) {
            return $this->quote_simple_table_name($name);
        }
        $parts = $this->get_table_name_parts($name);
        foreach ($parts as $i => $part) {
            $parts[$i] = $this->quote_simple_table_name($part);
        }
        return implode('.', $parts);
    }
    /**
     * Splits full table name into parts
     * @param string $name
     * @return array
     * @since 2.0.22
     */
    protected function get_table_name_parts($name)
    {
        return explode('.', $name);
    }
    /**
     * Quotes a column name for use in a query.
     * If the column name contains prefix, the prefix will also be properly quoted.
     * If the column name is already quoted or contains '(', '[[' or '{{',
     * then this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     * @see quoteSimpleColumnName()
     */
    public function quote_column_name($name)
    {
        if (strpos($name, '(') !== false || strpos($name, '[[') !== false) {
            return $name;
        }
        if (($pos = strrpos($name, '.')) !== false) {
            $prefix = $this->quote_table_name(substr($name, 0, $pos)) . '.';
            $name = substr($name, $pos + 1);
        } else {
            $prefix = '';
        }
        if (strpos($name, '{{') !== false) {
            return $name;
        }
        return $prefix . $this->quote_simple_column_name($name);
    }
    /**
     * Quotes a simple table name for use in a query.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is already quoted, this method will do nothing.
     * @param string $name table name
     * @return string the properly quoted table name
     */
    public function quote_simple_table_name(string $name)
    {
        if (is_string($this->table_quote_character)) {
            $starting_character = $ending_character = $this->table_quote_character;
        } else {
            [$starting_character, $ending_character] = $this->table_quote_character;
        }
        return strpos($name, $starting_character) !== false ? $name : $starting_character . $name . $ending_character;
    }
    /**
     * Quotes a simple column name for use in a query.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is already quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name
     * @return string the properly quoted column name
     */
    public function quote_simple_column_name($name)
    {
        if (is_string($this->column_quote_character)) {
            $starting_character = $ending_character = $this->column_quote_character;
        } else {
            [$starting_character, $ending_character] = $this->column_quote_character;
        }
        return $name === '*' || strpos($name, $starting_character) !== false ? $name : $starting_character . $name . $ending_character;
    }
    /**
     * Unquotes a simple table name.
     * A simple table name should contain the table name only without any schema prefix.
     * If the table name is not quoted, this method will do nothing.
     * @param string $name table name.
     * @return string unquoted table name.
     * @since 2.0.14
     */
    public function unquote_simple_table_name($name)
    {
        if (is_string($this->table_quote_character)) {
            $starting_character = $this->table_quote_character;
        } else {
            $starting_character = $this->table_quote_character[0];
        }
        return strpos($name, $starting_character) === false ? $name : substr($name, 1, -1);
    }
    /**
     * Unquotes a simple column name.
     * A simple column name should contain the column name only without any prefix.
     * If the column name is not quoted or is the asterisk character '*', this method will do nothing.
     * @param string $name column name.
     * @return string unquoted column name.
     * @since 2.0.14
     */
    public function unquote_simple_column_name($name)
    {
        if (is_string($this->column_quote_character)) {
            $starting_character = $this->column_quote_character;
        } else {
            $starting_character = $this->column_quote_character[0];
        }
        return strpos($name, $starting_character) === false ? $name : substr($name, 1, -1);
    }
    /**
     * Returns the actual name of a given table name.
     * This method will strip off curly brackets from the given table name
     * and replace the percentage character '%' with [[Connection::tablePrefix]].
     * @param string $name the table name to be converted
     * @return string the real name of the given table name
     */
    public function get_raw_table_name($name)
    {
        if (strpos($name, '{{') !== false) {
            $name = preg_replace('/\{\{(.*?)\}\}/', '\1', $name);
            return str_replace('%', $this->db->table_prefix, $name);
        }
        return $name;
    }
    /**
     * Extracts the PHP type from abstract DB type.
     * @param ColumnSchema $column the column schema information
     * @return string PHP type name
     */
    protected function get_column_php_type($column)
    {
        static $type_map = [
            // abstract type => php type
            self::TYPE_TINYINT => 'integer',
            self::TYPE_SMALLINT => 'integer',
            self::TYPE_INTEGER => 'integer',
            self::TYPE_BIGINT => 'integer',
            self::TYPE_BOOLEAN => 'boolean',
            self::TYPE_FLOAT => 'double',
            self::TYPE_DOUBLE => 'double',
            self::TYPE_BINARY => 'resource',
            self::TYPE_JSON => 'array',
        ];
        if (isset($type_map[$column->type])) {
            if ($column->type === 'bigint') {
                return PHP_INT_SIZE === 8 && !$column->unsigned ? 'integer' : 'string';
            }
            if ($column->type === 'integer') {
                return PHP_INT_SIZE === 4 && $column->unsigned ? 'string' : 'integer';
            }
            return $type_map[$column->type];
        }
        return 'string';
    }
    /**
     * Converts a DB exception to a more concrete one if possible.
     *
     * @param string $rawSql SQL that produced exception
     * @return Exception
     */
    public function convert_exception(\Exception $e, $raw_sql)
    {
        if ($e instanceof Exception) {
            return $e;
        }
        $exception_class = '\yii\db\Exception';
        foreach ($this->exception_map as $error => $class) {
            if (strpos($e->get_message(), (string) $error) !== false) {
                $exception_class = $class;
            }
        }
        $message = $e->get_message() . "\nThe SQL being executed was: {$raw_sql}";
        $error_info = $e instanceof \PDOException ? $e->error_info : null;
        return new $exception_class($message, $error_info, $e->get_code(), $e);
    }
    /**
     * Returns a value indicating whether a SQL statement is for read purpose.
     * @param string $sql the SQL statement
     * @return bool whether a SQL statement is for read purpose.
     */
    public function is_read_query($sql)
    {
        $pattern = '/^\s*(SELECT|SHOW|DESCRIBE)\b/i';
        return preg_match($pattern, $sql) > 0;
    }
    /**
     * Returns a server version as a string comparable by [[\version_compare()]].
     * @return string server version as a string.
     * @since 2.0.14
     */
    public function get_server_version()
    {
        if ($this->_server_version === null) {
            $this->_server_version = $this->db->get_slave_pdo(true)->get_attribute(\PDO::ATTR_SERVER_VERSION);
        }
        return $this->_server_version;
    }
    /**
     * Returns the cache key for the specified table name.
     * @param string $name the table name.
     * @return mixed the cache key.
     */
    protected function get_cache_key($name)
    {
        return [self::class, $this->db->dsn, $this->db->username, $this->get_raw_table_name($name)];
    }
    /**
     * Returns the cache tag name.
     * This allows [[refresh()]] to invalidate all cached table schemas.
     * @return string the cache tag name
     */
    protected function get_cache_tag()
    {
        return md5(serialize([self::class, $this->db->dsn, $this->db->username]));
    }
    /**
     * Returns the metadata of the given type for the given table.
     * If there's no metadata in the cache, this method will call
     * a `'loadTable' . ucfirst($type)` named method with the table name to obtain the metadata.
     * @param string $name table name. The table name may contain schema name if any. Do not quote the table name.
     * @param string $type metadata type.
     * @param bool $refresh whether to reload the table metadata even if it is found in the cache.
     * @return mixed metadata.
     * @since 2.0.13
     */
    protected function get_table_metadata($name, $type, $refresh)
    {
        $cache = null;
        if ($this->db->enable_schema_cache && !in_array($name, $this->db->schema_cache_exclude, true)) {
            $schema_cache = is_string($this->db->schema_cache) ? Yii::$app->get($this->db->schema_cache, false) : $this->db->schema_cache;
            if ($schema_cache instanceof Cache_Interface) {
                $cache = $schema_cache;
            }
        }
        $raw_name = $this->get_raw_table_name($name);
        if (!isset($this->_table_metadata[$raw_name])) {
            $this->load_table_metadata_from_cache($cache, $raw_name);
        }
        if ($refresh || !array_key_exists($type, $this->_table_metadata[$raw_name])) {
            $this->_table_metadata[$raw_name][$type] = $this->{'loadTable' . ucfirst($type)}($raw_name);
            $this->save_table_metadata_to_cache($cache, $raw_name);
        }
        return $this->_table_metadata[$raw_name][$type];
    }
    /**
     * Returns the metadata of the given type for all tables in the given schema.
     * This method will call a `'getTable' . ucfirst($type)` named method with the table name
     * and the refresh flag to obtain the metadata.
     * @param string $schema the schema of the metadata. Defaults to empty string, meaning the current or default schema name.
     * @param string $type metadata type.
     * @param bool $refresh whether to fetch the latest available table metadata. If this is `false`,
     * cached data may be returned if available.
     * @return array array of metadata.
     * @since 2.0.13
     */
    protected function get_schema_metadata($schema, $type, $refresh)
    {
        $metadata = [];
        $method_name = 'getTable' . ucfirst($type);
        foreach ($this->get_table_names($schema, $refresh) as $name) {
            if ($schema !== '') {
                $name = $schema . '.' . $name;
            }
            $table_metadata = $this->{$method_name}($name, $refresh);
            if ($table_metadata !== null) {
                $metadata[] = $table_metadata;
            }
        }
        return $metadata;
    }
    /**
     * Sets the metadata of the given type for the given table.
     * @param string $name table name.
     * @param string $type metadata type.
     * @param mixed $data metadata.
     * @since 2.0.13
     */
    protected function set_table_metadata($name, $type, $data)
    {
        $this->_table_metadata[$this->get_raw_table_name($name)][$type] = $data;
    }
    /**
     * Changes row's array key case to lower if PDO's one is set to uppercase.
     * @param array $row row's array or an array of row's arrays.
     * @param bool $multiple whether multiple rows or a single row passed.
     * @return array normalized row or rows.
     * @since 2.0.13
     */
    protected function normalize_pdo_row_key_case(array $row, $multiple)
    {
        if ($this->db->get_slave_pdo(true)->get_attribute(\PDO::ATTR_CASE) !== \PDO::CASE_UPPER) {
            return $row;
        }
        if ($multiple) {
            return array_map(fn(array $row) => array_change_key_case($row, CASE_LOWER), $row);
        }
        return array_change_key_case($row, CASE_LOWER);
    }
    /**
     * Tries to load and populate table metadata from cache.
     * @param Cache|null $cache
     * @param string $name
     */
    private function load_table_metadata_from_cache(?\yii\caching\Cache_Interface $cache, $name): void
    {
        if ($cache === null) {
            $this->_table_metadata[$name] = [];
            return;
        }
        $metadata = $cache->get($this->get_cache_key($name));
        if (!is_array($metadata) || !isset($metadata['cacheVersion']) || $metadata['cacheVersion'] !== static::SCHEMA_CACHE_VERSION) {
            $this->_table_metadata[$name] = [];
            return;
        }
        unset($metadata['cacheVersion']);
        $this->_table_metadata[$name] = $metadata;
    }
    /**
     * Saves table metadata to cache.
     * @param Cache|null $cache
     * @param string $name
     */
    private function save_table_metadata_to_cache(?\yii\caching\Cache_Interface $cache, $name): void
    {
        if ($cache === null) {
            return;
        }
        $metadata = $this->_table_metadata[$name];
        $metadata['cacheVersion'] = static::SCHEMA_CACHE_VERSION;
        $cache->set($this->get_cache_key($name), $metadata, $this->db->schema_cache_duration, new Tag_Dependency(['tags' => $this->get_cache_tag()]));
    }
}