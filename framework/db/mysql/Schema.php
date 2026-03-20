<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mysql;

use Yii;
use yii\base\Invalid_Config_Exception;
use yii\base\Not_Supported_Exception;
use yii\db\Check_Constraint;
use yii\db\Constraint;
use yii\db\Constraint_Finder_Interface;
use yii\db\Constraint_Finder_Trait;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Foreign_Key_Constraint;
use yii\db\Index_Constraint;
use yii\db\Schema as BaseSchema;
use yii\db\Table_Schema;
use yii\helpers\Array_Helper;
/**
 * Schema is the class for retrieving metadata from a MySQL database (version 4.1.x and 5.x).
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
     * {@inheritdoc}
     */
    public $column_schema_class = 'yii\db\mysql\ColumnSchema';
    /**
     * @var bool whether MySQL used is older than 5.1.
     */
    private ?bool $_old_mysql = null;
    /**
     * @var array mapping from physical column types (keys) to abstract column types (values)
     */
    public $type_map = ['tinyint' => self::TYPE_TINYINT, 'bool' => self::TYPE_TINYINT, 'boolean' => self::TYPE_TINYINT, 'bit' => self::TYPE_INTEGER, 'smallint' => self::TYPE_SMALLINT, 'mediumint' => self::TYPE_INTEGER, 'int' => self::TYPE_INTEGER, 'integer' => self::TYPE_INTEGER, 'bigint' => self::TYPE_BIGINT, 'float' => self::TYPE_FLOAT, 'double' => self::TYPE_DOUBLE, 'double precision' => self::TYPE_DOUBLE, 'real' => self::TYPE_FLOAT, 'decimal' => self::TYPE_DECIMAL, 'numeric' => self::TYPE_DECIMAL, 'dec' => self::TYPE_DECIMAL, 'fixed' => self::TYPE_DECIMAL, 'tinytext' => self::TYPE_TEXT, 'mediumtext' => self::TYPE_TEXT, 'longtext' => self::TYPE_TEXT, 'longblob' => self::TYPE_BINARY, 'blob' => self::TYPE_BINARY, 'text' => self::TYPE_TEXT, 'varchar' => self::TYPE_STRING, 'string' => self::TYPE_STRING, 'char' => self::TYPE_CHAR, 'datetime' => self::TYPE_DATETIME, 'year' => self::TYPE_DATE, 'date' => self::TYPE_DATE, 'time' => self::TYPE_TIME, 'timestamp' => self::TYPE_TIMESTAMP, 'enum' => self::TYPE_STRING, 'set' => self::TYPE_STRING, 'binary' => self::TYPE_BINARY, 'varbinary' => self::TYPE_BINARY, 'json' => self::TYPE_JSON];
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
    protected function resolve_table_name($name): \yii\db\Table_Schema
    {
        $resolved_name = new Table_Schema();
        $parts = explode('.', str_replace('`', '', $name));
        if (isset($parts[1])) {
            $resolved_name->schema_name = $parts[0];
            $resolved_name->name = $parts[1];
        } else {
            $resolved_name->schema_name = $this->default_schema;
            $resolved_name->name = $name;
        }
        $resolved_name->full_name = ($resolved_name->schema_name !== $this->default_schema ? $resolved_name->schema_name . '.' : '') . $resolved_name->name;
        return $resolved_name;
    }
    /**
     * {@inheritdoc}
     */
    protected function find_table_names($schema = '')
    {
        $sql = 'SHOW TABLES';
        if ($schema !== '') {
            $sql .= ' FROM ' . $this->quote_simple_table_name($schema);
        }
        return $this->db->create_command($sql)->query_column();
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_schema($name): ?\yii\db\Table_Schema
    {
        $table = new Table_Schema();
        $this->resolve_table_names($table, $name);
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
     */
    protected function load_table_foreign_keys($table_name)
    {
        return $this->load_table_constraints($table_name, 'foreignKeys');
    }
    /**
     * {@inheritdoc}
     * @return \yii\db\IndexConstraint[]
     */
    protected function load_table_indexes($table_name): array
    {
        static $sql = <<<'SQL'
        SELECT
            `s`.`INDEX_NAME` AS `name`,
            `s`.`COLUMN_NAME` AS `column_name`,
            `s`.`NON_UNIQUE` ^ 1 AS `index_is_unique`,
            `s`.`INDEX_NAME` = 'PRIMARY' AS `index_is_primary`
        FROM `information_schema`.`STATISTICS` AS `s`
        WHERE `s`.`TABLE_SCHEMA` = COALESCE(:schemaName, DATABASE()) AND `s`.`INDEX_SCHEMA` = `s`.`TABLE_SCHEMA` AND `s`.`TABLE_NAME` = :tableName
        ORDER BY `s`.`SEQ_IN_INDEX` ASC
        SQL;
        $resolved_name = $this->resolve_table_name($table_name);
        $indexes = $this->db->create_command($sql, [':schemaName' => $resolved_name->schema_name, ':tableName' => $resolved_name->name])->query_all();
        $indexes = $this->normalize_pdo_row_key_case($indexes, true);
        $indexes = Array_Helper::index($indexes, null, 'name');
        $result = [];
        foreach ($indexes as $name => $index) {
            $result[] = new Index_Constraint(['isPrimary' => (bool) $index[0]['index_is_primary'], 'isUnique' => (bool) $index[0]['index_is_unique'], 'name' => $name !== 'PRIMARY' ? $name : null, 'columnNames' => Array_Helper::get_column($index, 'column_name')]);
        }
        return $result;
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
        $version = $this->db->get_server_version();
        // check version MySQL >= 8.0.16
        if (\stripos($version, 'MariaDb') === false && \version_compare($version, '8.0.16', '<')) {
            throw new Not_Supported_Exception('MySQL < 8.0.16 does not support check constraints.');
        }
        $checks = [];
        $sql = <<<SQL
        SELECT cc.CONSTRAINT_NAME as constraint_name, cc.CHECK_CLAUSE as check_clause
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS tc
        JOIN INFORMATION_SCHEMA.CHECK_CONSTRAINTS cc
        ON tc.CONSTRAINT_NAME = cc.CONSTRAINT_NAME
        WHERE tc.TABLE_NAME = :tableName AND tc.CONSTRAINT_TYPE = 'CHECK';
        SQL;
        $resolved_name = $this->resolve_table_name($table_name);
        $table_rows = $this->db->create_command($sql, [':tableName' => $resolved_name->name])->query_all();
        if ($table_rows === []) {
            return $checks;
        }
        $table_rows = $this->normalize_pdo_row_key_case($table_rows, true);
        foreach ($table_rows as $table_row) {
            $check = new Check_Constraint(['name' => $table_row['constraint_name'], 'expression' => $table_row['check_clause']]);
            $checks[] = $check;
        }
        return $checks;
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException if this method is called.
     */
    protected function load_table_default_values($table_name)
    {
        throw new Not_Supported_Exception('MySQL does not support default value constraints.');
    }
    /**
     * Creates a query builder for the MySQL database.
     * @return QueryBuilder query builder instance
     */
    public function create_query_builder()
    {
        return Yii::create_object(Query_Builder::class_name(), [$this->db]);
    }
    /**
     * Resolves the table name and schema name (if any).
     * @param TableSchema $table the table metadata object
     * @param string $name the table name
     */
    protected function resolve_table_names($table, $name)
    {
        $parts = explode('.', str_replace('`', '', $name));
        if (isset($parts[1])) {
            $table->schema_name = $parts[0];
            $table->name = $parts[1];
            $table->full_name = $table->schema_name . '.' . $table->name;
        } else {
            $table->full_name = $table->name = $parts[0];
        }
    }
    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return T the column schema object
     */
    protected function load_column_schema(array $info)
    {
        $column = $this->create_column_schema();
        $column->name = $info['field'];
        $column->allow_null = $info['null'] === 'YES';
        $column->is_primary_key = strpos($info['key'], 'PRI') !== false;
        $column->auto_increment = stripos($info['extra'], 'auto_increment') !== false;
        $column->comment = $info['comment'];
        $column->db_type = $info['type'];
        $column->unsigned = stripos($column->db_type, 'unsigned') !== false;
        $column->type = self::TYPE_STRING;
        if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $column->db_type, $matches)) {
            $type = strtolower($matches[1]);
            if (isset($this->type_map[$type])) {
                $column->type = $this->type_map[$type];
            }
            if (!empty($matches[2])) {
                if ($type === 'enum') {
                    preg_match_all("/'[^']*'/", $matches[2], $values);
                    foreach ($values[0] as $i => $value) {
                        $values[$i] = trim($value, "'");
                    }
                    $column->enum_values = $values;
                } else {
                    $values = explode(',', $matches[2]);
                    $column->size = $column->precision = (int) $values[0];
                    if (isset($values[1])) {
                        $column->scale = (int) $values[1];
                    }
                    if ($column->size === 1 && $type === 'bit') {
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
        }
        $column->php_type = $this->get_column_php_type($column);
        if (!$column->is_primary_key) {
            /**
             * When displayed in the INFORMATION_SCHEMA.COLUMNS table, a default CURRENT TIMESTAMP is displayed
             * as CURRENT_TIMESTAMP up until MariaDB 10.2.2, and as current_timestamp() from MariaDB 10.2.3.
             *
             * See details here: https://mariadb.com/kb/en/library/now/#description
             */
            if (in_array($column->type, ['timestamp', 'datetime', 'date', 'time']) && isset($info['default']) && preg_match('/^current_timestamp(?:\(([0-9]*)\))?$/i', $info['default'], $matches)) {
                $column->default_value = new Expression('CURRENT_TIMESTAMP' . (!empty($matches[1]) ? '(' . $matches[1] . ')' : ''));
            } elseif (isset($type) && $type === 'bit') {
                $column->default_value = bindec(trim($info['default'] ?? '', 'b\''));
            } else {
                $column->default_value = $column->php_typecast($info['default']);
            }
        }
        return $column;
    }
    /**
     * Collects the metadata of table columns.
     * @param TableSchema $table the table metadata
     * @return bool whether the table exists in the database
     * @throws \Exception if DB query fails
     */
    protected function find_columns(\yii\db\Table_Schema $table): bool
    {
        $sql = 'SHOW FULL COLUMNS FROM ' . $this->quote_table_name($table->full_name);
        try {
            $columns = $this->db->create_command($sql)->query_all();
        } catch (\Exception $e) {
            $previous = $e->get_previous();
            if ($previous instanceof \PDOException && strpos($previous->get_message(), 'SQLSTATE[42S02') !== false) {
                // table does not exist
                // https://dev.mysql.com/doc/refman/5.5/en/error-messages-server.html#error_er_bad_table_error
                return false;
            }
            throw $e;
        }
        $json_columns = $this->get_json_columns($table);
        foreach ($columns as $info) {
            if ($this->db->slave_pdo->get_attribute(\PDO::ATTR_CASE) !== \PDO::CASE_LOWER) {
                $info = array_change_key_case($info, CASE_LOWER);
            }
            if (\in_array($info['field'], $json_columns, true)) {
                $info['type'] = static::TYPE_JSON;
            }
            $column = $this->load_column_schema($info);
            $table->columns[$column->name] = $column;
            if ($column->is_primary_key) {
                $table->primary_key[] = $column->name;
                if ($column->auto_increment) {
                    $table->sequence_name = '';
                }
            }
        }
        return true;
    }
    /**
     * Gets the CREATE TABLE sql string.
     * @param TableSchema $table the table metadata
     * @return string $sql the result of 'SHOW CREATE TABLE'
     */
    protected function get_create_table_sql($table)
    {
        $row = $this->db->create_command('SHOW CREATE TABLE ' . $this->quote_table_name($table->full_name))->query_one();
        if (isset($row['Create Table'])) {
            return $row['Create Table'];
        }
        $row = array_values($row);
        return $row[1];
    }
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     * @throws \Exception
     */
    protected function find_constraints($table)
    {
        $sql = <<<'SQL'
        SELECT
            `kcu`.`CONSTRAINT_NAME` AS `constraint_name`,
            `kcu`.`COLUMN_NAME` AS `column_name`,
            `kcu`.`REFERENCED_TABLE_NAME` AS `referenced_table_name`,
            `kcu`.`REFERENCED_COLUMN_NAME` AS `referenced_column_name`
        FROM `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`
        JOIN `information_schema`.`KEY_COLUMN_USAGE` AS `kcu` ON
            (
                `kcu`.`CONSTRAINT_CATALOG` = `rc`.`CONSTRAINT_CATALOG` OR
                (`kcu`.`CONSTRAINT_CATALOG` IS NULL AND `rc`.`CONSTRAINT_CATALOG` IS NULL)
            ) AND
            `kcu`.`CONSTRAINT_SCHEMA` = `rc`.`CONSTRAINT_SCHEMA` AND
            `kcu`.`CONSTRAINT_NAME` = `rc`.`CONSTRAINT_NAME`
        WHERE `rc`.`CONSTRAINT_SCHEMA` = database() AND `kcu`.`TABLE_SCHEMA` = database()
        AND `rc`.`TABLE_NAME` = :tableName AND `kcu`.`TABLE_NAME` = :tableName1
        SQL;
        try {
            $rows = $this->db->create_command($sql, [':tableName' => $table->name, ':tableName1' => $table->name])->query_all();
            $constraints = [];
            foreach ($rows as $row) {
                $constraints[$row['constraint_name']]['referenced_table_name'] = $row['referenced_table_name'];
                $constraints[$row['constraint_name']]['columns'][$row['column_name']] = $row['referenced_column_name'];
            }
            $table->foreign_keys = [];
            foreach ($constraints as $name => $constraint) {
                $table->foreign_keys[$name] = array_merge([$constraint['referenced_table_name']], $constraint['columns']);
            }
        } catch (\Exception $e) {
            $previous = $e->get_previous();
            if (!$previous instanceof \PDOException || strpos($previous->get_message(), 'SQLSTATE[42S02') === false) {
                throw $e;
            }
            // table does not exist, try to determine the foreign keys using the table creation sql
            $sql = $this->get_create_table_sql($table);
            $regexp = '/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
            if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fks = array_map('trim', explode(',', str_replace(['`', '"'], '', $match[1])));
                    $pks = array_map('trim', explode(',', str_replace(['`', '"'], '', $match[3])));
                    $constraint = [str_replace(['`', '"'], '', $match[2])];
                    foreach ($fks as $k => $name) {
                        $constraint[$name] = $pks[$k];
                    }
                    $table->foreign_keys[md5(serialize($constraint))] = $constraint;
                }
                $table->foreign_keys = array_values($table->foreign_keys);
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
        $sql = $this->get_create_table_sql($table);
        $unique_indexes = [];
        $regexp = '/UNIQUE KEY\s+[`"](.+)[`"]\s*\(([`"].+[`"])+\)/mi';
        if (preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $index_name = $match[1];
                $index_columns = array_map('trim', preg_split('/[`"],[`"]/', trim($match[2], '`"')));
                $unique_indexes[$index_name] = $index_columns;
            }
        }
        return $unique_indexes;
    }
    /**
     * {@inheritdoc}
     */
    public function create_column_schema_builder($type, $length = null)
    {
        return Yii::create_object(Column_Schema_Builder::class_name(), [$type, $length, $this->db]);
    }
    /**
     * @return bool whether the version of the MySQL being used is older than 5.1.
     * @throws InvalidConfigException
     * @throws Exception
     * @since 2.0.13
     */
    protected function is_old_mysql()
    {
        if ($this->_old_mysql === null) {
            $version = $this->db->get_slave_pdo(true)->get_attribute(\PDO::ATTR_SERVER_VERSION);
            $this->_old_mysql = version_compare($version, '5.1', '<=');
        }
        return $this->_old_mysql;
    }
    /**
     * Loads multiple types of constraints and returns the specified ones.
     * @param string $tableName table name.
     * @param string $returnType return type:
     * - primaryKey
     * - foreignKeys
     * - uniques
     * @return mixed constraints.
     */
    private function load_table_constraints($table_name, string $return_type)
    {
        static $sql = <<<'SQL'
        SELECT
            `kcu`.`CONSTRAINT_NAME` AS `name`,
            `kcu`.`COLUMN_NAME` AS `column_name`,
            `tc`.`CONSTRAINT_TYPE` AS `type`,
            CASE
                WHEN :schemaName IS NULL AND `kcu`.`REFERENCED_TABLE_SCHEMA` = DATABASE() THEN NULL
                ELSE `kcu`.`REFERENCED_TABLE_SCHEMA`
            END AS `foreign_table_schema`,
            `kcu`.`REFERENCED_TABLE_NAME` AS `foreign_table_name`,
            `kcu`.`REFERENCED_COLUMN_NAME` AS `foreign_column_name`,
            `rc`.`UPDATE_RULE` AS `on_update`,
            `rc`.`DELETE_RULE` AS `on_delete`,
            `kcu`.`ORDINAL_POSITION` AS `position`
        FROM
            `information_schema`.`KEY_COLUMN_USAGE` AS `kcu`,
            `information_schema`.`REFERENTIAL_CONSTRAINTS` AS `rc`,
            `information_schema`.`TABLE_CONSTRAINTS` AS `tc`
        WHERE
            `kcu`.`TABLE_SCHEMA` = COALESCE(:schemaName1, DATABASE()) AND `kcu`.`CONSTRAINT_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `kcu`.`TABLE_NAME` = :tableName
            AND `rc`.`CONSTRAINT_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `rc`.`TABLE_NAME` = :tableName1 AND `rc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME`
            AND `tc`.`TABLE_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `tc`.`TABLE_NAME` = :tableName2 AND `tc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME` AND `tc`.`CONSTRAINT_TYPE` = 'FOREIGN KEY'
        UNION
        SELECT
            `kcu`.`CONSTRAINT_NAME` AS `name`,
            `kcu`.`COLUMN_NAME` AS `column_name`,
            `tc`.`CONSTRAINT_TYPE` AS `type`,
            NULL AS `foreign_table_schema`,
            NULL AS `foreign_table_name`,
            NULL AS `foreign_column_name`,
            NULL AS `on_update`,
            NULL AS `on_delete`,
            `kcu`.`ORDINAL_POSITION` AS `position`
        FROM
            `information_schema`.`KEY_COLUMN_USAGE` AS `kcu`,
            `information_schema`.`TABLE_CONSTRAINTS` AS `tc`
        WHERE
            `kcu`.`TABLE_SCHEMA` = COALESCE(:schemaName2, DATABASE()) AND `kcu`.`TABLE_NAME` = :tableName3
            AND `tc`.`TABLE_SCHEMA` = `kcu`.`TABLE_SCHEMA` AND `tc`.`TABLE_NAME` = :tableName4 AND `tc`.`CONSTRAINT_NAME` = `kcu`.`CONSTRAINT_NAME` AND `tc`.`CONSTRAINT_TYPE` IN ('PRIMARY KEY', 'UNIQUE')
        ORDER BY `position` ASC
        SQL;
        $resolved_name = $this->resolve_table_name($table_name);
        $constraints = $this->db->create_command($sql, [':schemaName' => $resolved_name->schema_name, ':schemaName1' => $resolved_name->schema_name, ':schemaName2' => $resolved_name->schema_name, ':tableName' => $resolved_name->name, ':tableName1' => $resolved_name->name, ':tableName2' => $resolved_name->name, ':tableName3' => $resolved_name->name, ':tableName4' => $resolved_name->name])->query_all();
        $constraints = $this->normalize_pdo_row_key_case($constraints, true);
        $constraints = Array_Helper::index($constraints, null, ['type', 'name']);
        $result = ['primaryKey' => null, 'foreignKeys' => [], 'uniques' => []];
        foreach ($constraints as $type => $names) {
            foreach ($names as $name => $constraint) {
                switch ($type) {
                    case 'PRIMARY KEY':
                        $result['primaryKey'] = new Constraint(['columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                    case 'FOREIGN KEY':
                        $result['foreignKeys'][] = new Foreign_Key_Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name'), 'foreignSchemaName' => $constraint[0]['foreign_table_schema'], 'foreignTableName' => $constraint[0]['foreign_table_name'], 'foreignColumnNames' => Array_Helper::get_column($constraint, 'foreign_column_name'), 'onDelete' => $constraint[0]['on_delete'], 'onUpdate' => $constraint[0]['on_update']]);
                        break;
                    case 'UNIQUE':
                        $result['uniques'][] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                }
            }
        }
        foreach ($result as $type => $data) {
            $this->set_table_metadata($table_name, $type, $data);
        }
        return $result[$return_type];
    }
    private function get_json_columns(Table_Schema $table): array
    {
        $sql = $this->get_create_table_sql($table);
        $result = [];
        $regexp = '/json_valid\([\`"](.+)[\`"]\s*\)/mi';
        if (\preg_match_all($regexp, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $result[] = $match[1];
            }
        }
        return $result;
    }
}