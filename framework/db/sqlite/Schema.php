<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\sqlite;

use Yii;
use yii\base\Not_Supported_Exception;
use yii\db\Check_Constraint;
use yii\db\Column_Schema;
use yii\db\Constraint;
use yii\db\Constraint_Finder_Interface;
use yii\db\Constraint_Finder_Trait;
use yii\db\Expression;
use yii\db\Foreign_Key_Constraint;
use yii\db\Index_Constraint;
use yii\db\Schema as BaseSchema;
use yii\db\Sql_Token;
use yii\db\Table_Schema;
use yii\db\Transaction;
use yii\helpers\Array_Helper;
/**
 * Schema is the class for retrieving metadata from a SQLite (2/3) database.
 *
 * @property-write string $transactionIsolationLevel The transaction isolation level to use for this
 * transaction. This can be either [[Transaction::READ_UNCOMMITTED]] or [[Transaction::SERIALIZABLE]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of ColumnSchema = ColumnSchema
 * @extends BaseSchema<T>
 */
class Schema extends Base_Schema implements Constraint_Finder_Interface
{
    use Constraint_Finder_Trait;
    /**
     * @var array mapping from physical column types (keys) to abstract column types (values)
     */
    public $type_map = ['tinyint' => self::TYPE_TINYINT, 'bit' => self::TYPE_SMALLINT, 'boolean' => self::TYPE_BOOLEAN, 'bool' => self::TYPE_BOOLEAN, 'smallint' => self::TYPE_SMALLINT, 'mediumint' => self::TYPE_INTEGER, 'int' => self::TYPE_INTEGER, 'integer' => self::TYPE_INTEGER, 'bigint' => self::TYPE_BIGINT, 'float' => self::TYPE_FLOAT, 'double' => self::TYPE_DOUBLE, 'real' => self::TYPE_FLOAT, 'decimal' => self::TYPE_DECIMAL, 'numeric' => self::TYPE_DECIMAL, 'tinytext' => self::TYPE_TEXT, 'mediumtext' => self::TYPE_TEXT, 'longtext' => self::TYPE_TEXT, 'text' => self::TYPE_TEXT, 'varchar' => self::TYPE_STRING, 'string' => self::TYPE_STRING, 'char' => self::TYPE_CHAR, 'blob' => self::TYPE_BINARY, 'datetime' => self::TYPE_DATETIME, 'year' => self::TYPE_DATE, 'date' => self::TYPE_DATE, 'time' => self::TYPE_TIME, 'timestamp' => self::TYPE_TIMESTAMP, 'enum' => self::TYPE_STRING];
    /**
     * {@inheritdoc}
     */
    protected $table_quote_character = '`';
    /**
     * {@inheritdoc}
     */
    protected $column_quote_character = '`';
    /**
     * {@inheritdoc}
     */
    protected function find_table_names($schema = '')
    {
        $sql = "SELECT DISTINCT tbl_name FROM sqlite_master WHERE tbl_name<>'sqlite_sequence' ORDER BY tbl_name";
        return $this->db->create_command($sql)->query_column();
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_schema($name): ?\yii\db\Table_Schema
    {
        $table = new Table_Schema();
        $table->name = $name;
        $table->full_name = $name;
        if ($this->find_columns($table)) {
            $this->find_constraints($table);
            return $table;
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_primary_key($table_name)
    {
        return $this->load_table_constraints($table_name, 'primaryKey');
    }
    /**
     * {@inheritdoc}
     * @return \yii\db\ForeignKeyConstraint[]
     */
    protected function load_table_foreign_keys($table_name): array
    {
        $foreign_keys = $this->db->create_command('PRAGMA FOREIGN_KEY_LIST (' . $this->quote_value($table_name) . ')')->query_all();
        $foreign_keys = $this->normalize_pdo_row_key_case($foreign_keys, true);
        $foreign_keys = Array_Helper::index($foreign_keys, null, 'table');
        Array_Helper::multisort($foreign_keys, 'seq', SORT_ASC, SORT_NUMERIC);
        $result = [];
        foreach ($foreign_keys as $table => $foreign_key) {
            $result[] = new Foreign_Key_Constraint(['columnNames' => Array_Helper::get_column($foreign_key, 'from'), 'foreignTableName' => $table, 'foreignColumnNames' => Array_Helper::get_column($foreign_key, 'to'), 'onDelete' => $foreign_key[0]['on_delete'] ?? null, 'onUpdate' => $foreign_key[0]['on_update'] ?? null]);
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_indexes($table_name)
    {
        return $this->load_table_constraints($table_name, 'indexes');
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_uniques($table_name)
    {
        return $this->load_table_constraints($table_name, 'uniques');
    }
    /**
     * {@inheritdoc}
     * @return \yii\db\CheckConstraint[]
     */
    protected function load_table_checks($table_name): array
    {
        $sql = $this->db->create_command('SELECT `sql` FROM `sqlite_master` WHERE name = :tableName', [':tableName' => $table_name])->query_scalar();
        /** @var SqlToken[]|SqlToken[][]|SqlToken[][][] $code */
        $code = (new Sql_Tokenizer($sql))->tokenize();
        $pattern = (new Sql_Tokenizer('any CREATE any TABLE any()'))->tokenize();
        if (!$code[0]->matches($pattern, 0, $first_match_index, $last_match_index)) {
            return [];
        }
        $create_table_token = $code[0][$last_match_index - 1];
        $result = [];
        $offset = 0;
        while (true) {
            $pattern = (new Sql_Tokenizer('any CHECK()'))->tokenize();
            if (!$create_table_token->matches($pattern, $offset, $first_match_index, $offset)) {
                break;
            }
            $check_sql = $create_table_token[$offset - 1]->get_sql();
            $name = null;
            $pattern = (new Sql_Tokenizer('CONSTRAINT any'))->tokenize();
            if (isset($create_table_token[$first_match_index - 2]) && $create_table_token->matches($pattern, $first_match_index - 2)) {
                $name = $create_table_token[$first_match_index - 1]->content;
            }
            $result[] = new Check_Constraint(['name' => $name, 'expression' => $check_sql]);
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException if this method is called.
     */
    protected function load_table_default_values($table_name)
    {
        throw new Not_Supported_Exception('SQLite does not support default value constraints.');
    }
    /**
     * Creates a query builder for the MySQL database.
     * This method may be overridden by child classes to create a DBMS-specific query builder.
     * @return QueryBuilder query builder instance
     */
    public function create_query_builder()
    {
        return Yii::create_object(Query_Builder::class_name(), [$this->db]);
    }
    /**
     * {@inheritdoc}
     * @return ColumnSchemaBuilder column schema builder instance
     */
    public function create_column_schema_builder($type, $length = null)
    {
        return Yii::create_object(Column_Schema_Builder::class_name(), [$type, $length]);
    }
    /**
     * Collects the table column metadata.
     * @param TableSchema $table the table metadata
     * @return bool whether the table exists in the database
     */
    protected function find_columns($table): bool
    {
        $sql = 'PRAGMA table_info(' . $this->quote_simple_table_name($table->name) . ')';
        $columns = $this->db->create_command($sql)->query_all();
        if (empty($columns)) {
            return false;
        }
        foreach ($columns as $info) {
            $column = $this->load_column_schema($info);
            $table->columns[$column->name] = $column;
            if ($column->is_primary_key) {
                $table->primary_key[] = $column->name;
            }
        }
        if (count($table->primary_key) === 1 && !strncasecmp($table->columns[$table->primary_key[0]]->db_type, 'int', 3)) {
            $table->sequence_name = '';
            $table->columns[$table->primary_key[0]]->auto_increment = true;
        }
        return true;
    }
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function find_constraints($table)
    {
        $sql = 'PRAGMA foreign_key_list(' . $this->quote_simple_table_name($table->name) . ')';
        $keys = $this->db->create_command($sql)->query_all();
        foreach ($keys as $key) {
            $id = (int) $key['id'];
            if (!isset($table->foreign_keys[$id])) {
                $table->foreign_keys[$id] = [$key['table'], $key['from'] => $key['to']];
            } else {
                // composite FK
                $table->foreign_keys[$id][$key['from']] = $key['to'];
            }
        }
    }
    /**
     * Returns all unique indexes for the given table.
     *
     * Each array element is of the following structure:
     *
     * ```
     * [
     *     'IndexName1' => ['col1' [, ...]],
     *     'IndexName2' => ['col2' [, ...]],
     * ]
     * ```
     *
     * @param TableSchema $table the table metadata
     * @return array all unique indexes for the given table.
     */
    public function find_unique_indexes($table): array
    {
        $sql = 'PRAGMA index_list(' . $this->quote_simple_table_name($table->name) . ')';
        $indexes = $this->db->create_command($sql)->query_all();
        $unique_indexes = [];
        foreach ($indexes as $index) {
            $index_name = $index['name'];
            $index_info = $this->db->create_command('PRAGMA index_info(' . $this->quote_value($index['name']) . ')')->query_all();
            if ($index['unique']) {
                $unique_indexes[$index_name] = [];
                foreach ($index_info as $row) {
                    $unique_indexes[$index_name][] = $row['name'];
                }
            }
        }
        return $unique_indexes;
    }
    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return T the column schema object
     */
    protected function load_column_schema(array $info)
    {
        $column = $this->create_column_schema();
        $column->name = $info['name'];
        $column->allow_null = !$info['notnull'];
        $column->is_primary_key = $info['pk'] != 0;
        $column->db_type = strtolower($info['type']);
        $column->unsigned = strpos($column->db_type, 'unsigned') !== false;
        $column->type = self::TYPE_STRING;
        if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $column->db_type, $matches)) {
            $type = strtolower($matches[1]);
            if (isset($this->type_map[$type])) {
                $column->type = $this->type_map[$type];
            }
            if (!empty($matches[2])) {
                $values = explode(',', $matches[2]);
                $column->size = $column->precision = (int) $values[0];
                if (isset($values[1])) {
                    $column->scale = (int) $values[1];
                }
                if ($column->size === 1 && ($type === 'tinyint' || $type === 'bit')) {
                    $column->type = 'boolean';
                } elseif ($type === 'bit') {
                    if ($column->size > 32) {
                        $column->type = 'bigint';
                    } elseif ($column->size === 32) {
                        $column->type = 'integer';
                    }
                }
            }
        }
        $column->php_type = $this->get_column_php_type($column);
        if (!$column->is_primary_key) {
            if ($info['dflt_value'] === 'null' || $info['dflt_value'] === '' || $info['dflt_value'] === null) {
                $column->default_value = null;
            } elseif ($column->type === 'timestamp' && $info['dflt_value'] === 'CURRENT_TIMESTAMP') {
                $column->default_value = new Expression('CURRENT_TIMESTAMP');
            } else {
                $value = trim($info['dflt_value'], "'\"");
                $column->default_value = $column->php_typecast($value);
            }
        }
        return $column;
    }
    /**
     * Sets the isolation level of the current transaction.
     * @param string $level The transaction isolation level to use for this transaction.
     * This can be either [[Transaction::READ_UNCOMMITTED]] or [[Transaction::SERIALIZABLE]].
     * @throws NotSupportedException when unsupported isolation levels are used.
     * SQLite only supports SERIALIZABLE and READ UNCOMMITTED.
     * @see https://www.sqlite.org/pragma.html#pragma_read_uncommitted
     */
    public function set_transaction_isolation_level($level): void
    {
        switch ($level) {
            case Transaction::SERIALIZABLE:
                $this->db->create_command('PRAGMA read_uncommitted = False;')->execute();
                break;
            case Transaction::READ_UNCOMMITTED:
                $this->db->create_command('PRAGMA read_uncommitted = True;')->execute();
                break;
            default:
                throw new Not_Supported_Exception(get_class($this) . ' only supports transaction isolation levels READ UNCOMMITTED and SERIALIZABLE.');
        }
    }
    /**
     * Returns table columns info.
     * @param string $tableName table name
     * @return array
     */
    private function load_table_columns_info($table_name)
    {
        $table_columns = $this->db->create_command('PRAGMA TABLE_INFO (' . $this->quote_value($table_name) . ')')->query_all();
        $table_columns = $this->normalize_pdo_row_key_case($table_columns, true);
        return Array_Helper::index($table_columns, 'cid');
    }
    /**
     * Loads multiple types of constraints and returns the specified ones.
     * @param string $tableName table name.
     * @param string $returnType return type:
     * - primaryKey
     * - indexes
     * - uniques
     * @return mixed constraints.
     */
    private function load_table_constraints($table_name, string $return_type)
    {
        $indexes = $this->db->create_command('PRAGMA INDEX_LIST (' . $this->quote_value($table_name) . ')')->query_all();
        $indexes = $this->normalize_pdo_row_key_case($indexes, true);
        $table_columns = null;
        if (!empty($indexes) && !isset($indexes[0]['origin'])) {
            /*
             * SQLite may not have an "origin" column in INDEX_LIST
             * See https://www.sqlite.org/src/info/2743846cdba572f6
             */
            $table_columns = $this->load_table_columns_info($table_name);
        }
        $result = ['primaryKey' => null, 'indexes' => [], 'uniques' => []];
        foreach ($indexes as $index) {
            $columns = $this->db->create_command('PRAGMA INDEX_INFO (' . $this->quote_value($index['name']) . ')')->query_all();
            $columns = $this->normalize_pdo_row_key_case($columns, true);
            Array_Helper::multisort($columns, 'seqno', SORT_ASC, SORT_NUMERIC);
            if ($table_columns !== null) {
                // SQLite may not have an "origin" column in INDEX_LIST
                $index['origin'] = 'c';
                if (!empty($columns) && $table_columns[$columns[0]['cid']]['pk'] > 0) {
                    $index['origin'] = 'pk';
                } elseif ($index['unique'] && $this->is_system_identifier($index['name'])) {
                    $index['origin'] = 'u';
                }
            }
            $result['indexes'][] = new Index_Constraint(['isPrimary' => $index['origin'] === 'pk', 'isUnique' => (bool) $index['unique'], 'name' => $index['name'], 'columnNames' => Array_Helper::get_column($columns, 'name')]);
            if ($index['origin'] === 'u') {
                $result['uniques'][] = new Constraint(['name' => $index['name'], 'columnNames' => Array_Helper::get_column($columns, 'name')]);
            } elseif ($index['origin'] === 'pk') {
                $result['primaryKey'] = new Constraint(['columnNames' => Array_Helper::get_column($columns, 'name')]);
            }
        }
        if ($result['primaryKey'] === null) {
            /*
             * Additional check for PK in case of INTEGER PRIMARY KEY with ROWID
             * See https://www.sqlite.org/lang_createtable.html#primkeyconst
             */
            if ($table_columns === null) {
                $table_columns = $this->load_table_columns_info($table_name);
            }
            foreach ($table_columns as $table_column) {
                if ($table_column['pk'] > 0) {
                    $result['primaryKey'] = new Constraint(['columnNames' => [$table_column['name']]]);
                    break;
                }
            }
        }
        foreach ($result as $type => $data) {
            $this->set_table_metadata($table_name, $type, $data);
        }
        return $result[$return_type];
    }
    /**
     * Return whether the specified identifier is a SQLite system identifier.
     * @param string $identifier
     * @see https://www.sqlite.org/src/artifact/74108007d286232f
     */
    private function is_system_identifier($identifier): bool
    {
        return strncmp($identifier, 'sqlite_', 7) === 0;
    }
    /**
     * @inheritdoc
     *
     * Since PHP 8.5, `PDO::quote()` throws a ValueError when the string contains null bytes ("\0").
     *
     * This method sanitizes such bytes before calling the parent implementation to avoid exceptions while maintaining
     * backward compatibility.
     *
     * @link https://github.com/php/php-src/commit/0a10f6db26875e0f1d0f867307cee591d29a43c7
     */
    public function quote_value($value)
    {
        if (PHP_VERSION_ID >= 80500 && is_string($value) && str_contains($value, "\x00")) {
            // Sanitize null bytes to prevent PDO ValueError on PHP 8.5+
            $value = str_replace("\x00", '', $value);
        }
        return parent::quote_value($value);
    }
}