<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\sqlite;

use yii\base\InvalidArgumentException;
use yii\base\Not_Supported_Exception;
use yii\db\Connection;
use yii\db\Expression;
use yii\db\Expression_Interface;
use yii\db\Query;
use yii\helpers\String_Helper;
/**
 * QueryBuilder is the query builder for SQLite databases.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Query_Builder extends \yii\db\Query_Builder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $type_map = [Schema::TYPE_PK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', Schema::TYPE_UPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', Schema::TYPE_BIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', Schema::TYPE_UBIGPK => 'integer PRIMARY KEY AUTOINCREMENT NOT NULL', Schema::TYPE_CHAR => 'char(1)', Schema::TYPE_STRING => 'varchar(255)', Schema::TYPE_TEXT => 'text', Schema::TYPE_TINYINT => 'tinyint', Schema::TYPE_SMALLINT => 'smallint', Schema::TYPE_INTEGER => 'integer', Schema::TYPE_BIGINT => 'bigint', Schema::TYPE_FLOAT => 'float', Schema::TYPE_DOUBLE => 'double', Schema::TYPE_DECIMAL => 'decimal(10,0)', Schema::TYPE_DATETIME => 'datetime', Schema::TYPE_TIMESTAMP => 'timestamp', Schema::TYPE_TIME => 'time', Schema::TYPE_DATE => 'date', Schema::TYPE_BINARY => 'blob', Schema::TYPE_BOOLEAN => 'boolean', Schema::TYPE_MONEY => 'decimal(19,4)'];
    /**
     * {@inheritdoc}
     */
    protected function default_expression_builders()
    {
        return array_merge(parent::default_expression_builders(), ['yii\db\conditions\LikeCondition' => 'yii\db\sqlite\conditions\LikeConditionBuilder', 'yii\db\conditions\InCondition' => 'yii\db\sqlite\conditions\InConditionBuilder']);
    }
    /**
     * {@inheritdoc}
     * @see https://stackoverflow.com/questions/15277373/sqlite-upsert-update-or-insert/15277374#15277374
     */
    public function upsert($table, $insert_columns, $update_columns, &$params)
    {
        list($unique_names, $insert_names, $update_names) = $this->prepare_upsert_columns($table, $insert_columns, $update_columns, $constraints);
        if (empty($unique_names)) {
            return $this->insert($table, $insert_columns, $params);
        }
        if ($update_names === []) {
            // there are no columns to update
            $update_columns = false;
        }
        list(, $placeholders, $values, $params) = $this->prepare_insert_values($table, $insert_columns, $params);
        $insert_sql = 'INSERT OR IGNORE INTO ' . $this->db->quote_table_name($table) . (!empty($insert_names) ? ' (' . implode(', ', $insert_names) . ')' : '') . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : $values);
        if ($update_columns === false) {
            return $insert_sql;
        }
        $update_condition = ['or'];
        $quoted_table_name = $this->db->quote_table_name($table);
        foreach ($constraints as $constraint) {
            $constraint_condition = ['and'];
            foreach ($constraint->column_names as $name) {
                $quoted_name = $this->db->quote_column_name($name);
                $constraint_condition[] = "{$quoted_table_name}.{$quoted_name}=(SELECT {$quoted_name} FROM `EXCLUDED`)";
            }
            $update_condition[] = $constraint_condition;
        }
        if ($update_columns === true) {
            $update_columns = [];
            foreach ($update_names as $name) {
                $quoted_name = $this->db->quote_column_name($name);
                if (strrpos($quoted_name, '.') === false) {
                    $quoted_name = "(SELECT {$quoted_name} FROM `EXCLUDED`)";
                }
                $update_columns[$name] = new Expression($quoted_name);
            }
        }
        $update_sql = 'WITH "EXCLUDED" (' . implode(', ', $insert_names) . ') AS (' . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')' : ltrim($values, ' ')) . ') ' . $this->update($table, $update_columns, $update_condition, $params);
        return "{$update_sql}; {$insert_sql};";
    }
    /**
     * Generates a batch INSERT SQL statement.
     *
     * For example,
     *
     * ```
     * $connection->createCommand()->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ])->execute();
     * ```
     *
     * Note that the values in each row must match the corresponding column names.
     *
     * @param string $table the table that new rows will be inserted into.
     * @param array $columns the column names
     * @param array|\Generator $rows the rows to be batch inserted into the table
     * @return string the batch INSERT SQL statement
     */
    public function batch_insert($table, $columns, $rows, &$params = [])
    {
        if (empty($rows)) {
            return '';
        }
        // SQLite supports batch insert natively since 3.7.11
        // https://www.sqlite.org/releaselog/3_7_11.html
        $this->db->open();
        // ensure pdo is not null
        if (version_compare($this->db->get_server_version(), '3.7.11', '>=')) {
            return parent::batch_insert($table, $columns, $rows, $params);
        }
        $schema = $this->db->get_schema();
        if (($table_schema = $schema->get_table_schema($table)) !== null) {
            $column_schemas = $table_schema->columns;
        } else {
            $column_schemas = [];
        }
        $values = [];
        foreach ($rows as $row) {
            $vs = [];
            foreach ($row as $i => $value) {
                if (isset($column_schemas[$columns[$i]])) {
                    $value = $column_schemas[$columns[$i]]->db_typecast($value);
                }
                if (is_string($value)) {
                    $value = $schema->quote_value($value);
                } elseif (is_float($value)) {
                    // ensure type cast always has . as decimal separator in all locales
                    $value = String_Helper::float_to_string($value);
                } elseif ($value === false) {
                    $value = 0;
                } elseif ($value === null) {
                    $value = 'NULL';
                } elseif ($value instanceof Expression_Interface) {
                    $value = $this->build_expression($value, $params);
                }
                $vs[] = $value;
            }
            $values[] = implode(', ', $vs);
        }
        if (empty($values)) {
            return '';
        }
        foreach ($columns as $i => $name) {
            $columns[$i] = $schema->quote_column_name($name);
        }
        return 'INSERT INTO ' . $schema->quote_table_name($table) . ' (' . implode(', ', $columns) . ') SELECT ' . implode(' UNION SELECT ', $values);
    }
    /**
     * Creates a SQL statement for resetting the sequence value of a table's primary key.
     * The sequence will be reset such that the primary key of the next new row inserted
     * will have the specified value or 1.
     * @param string $tableName the name of the table whose primary key sequence will be reset
     * @param mixed $value the value for the primary key of the next new row inserted. If this is not set,
     * the next new row's primary key will have a value 1.
     * @return string the SQL statement for resetting sequence
     * @throws InvalidArgumentException if the table does not exist or there is no sequence associated with the table.
     */
    public function reset_sequence($table_name, $value = null)
    {
        $db = $this->db;
        $table = $db->get_table_schema($table_name);
        if ($table !== null && $table->sequence_name !== null) {
            $table_name = $db->quote_table_name($table_name);
            if ($value === null) {
                $key = $this->db->quote_column_name(reset($table->primary_key));
                $value = $this->db->use_master(function (Connection $db) use ($key, $table_name) {
                    return $db->create_command("SELECT MAX({$key}) FROM {$table_name}")->query_scalar();
                });
            } else {
                $value = (int) $value - 1;
            }
            return "UPDATE sqlite_sequence SET seq='{$value}' WHERE name='{$table->name}'";
        } elseif ($table === null) {
            throw new InvalidArgumentException("Table not found: {$table_name}");
        }
        throw new InvalidArgumentException("There is not sequence associated with table '{$table_name}'.'");
    }
    /**
     * Enables or disables integrity check.
     * @param bool $check whether to turn on or off the integrity check.
     * @param string $schema the schema of the tables. Meaningless for SQLite.
     * @param string $table the table name. Meaningless for SQLite.
     * @return string the SQL statement for checking integrity
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function check_integrity($check = true, $schema = '', $table = '')
    {
        return 'PRAGMA foreign_keys=' . (int) $check;
    }
    /**
     * Builds a SQL statement for truncating a DB table.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return string the SQL statement for truncating a DB table.
     */
    public function truncate_table($table)
    {
        return 'DELETE FROM ' . $this->db->quote_table_name($table);
    }
    /**
     * Builds a SQL statement for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping an index.
     */
    public function drop_index($name, $table)
    {
        return 'DROP INDEX ' . $this->db->quote_table_name($name);
    }
    /**
     * Builds a SQL statement for dropping a DB column.
     * @param string $table the table whose column is to be dropped. The name will be properly quoted by the method.
     * @param string $column the name of the column to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a DB column.
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function drop_column($table, $column)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * Builds a SQL statement for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB column.
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function rename_column($table, $old_name, $new_name)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * Builds a SQL statement for adding a foreign key constraint to an existing table.
     * The method will properly quote the table and column names.
     * @param string $name the name of the foreign key constraint.
     * @param string $table the table that the foreign key constraint will be added to.
     * @param string|array $columns the name of the column to that the constraint will be added on.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string $refTable the table that the foreign key references to.
     * @param string|array $refColumns the name of the column that the foreign key references to.
     * If there are multiple columns, separate them with commas or use an array to represent them.
     * @param string|null $delete the ON DELETE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @param string|null $update the ON UPDATE option. Most DBMS support these options: RESTRICT, CASCADE, NO ACTION, SET DEFAULT, SET NULL
     * @return string the SQL statement for adding a foreign key constraint to an existing table.
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function add_foreign_key($name, $table, $columns, $ref_table, $ref_columns, $delete = null, $update = null)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a foreign key constraint.
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function drop_foreign_key($name, $table)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * Builds a SQL statement for renaming a DB table.
     *
     * @param string $table the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB table.
     */
    public function rename_table($table, $new_name)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' RENAME TO ' . $this->db->quote_table_name($new_name);
    }
    /**
     * Builds a SQL statement for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[getColumnType()]] method will be invoked to convert abstract
     * column type (if any) into the physical one. Anything that is not recognized as abstract type will be kept
     * in the generated SQL. For example, 'string' will be turned into 'varchar(255)', while 'string not null'
     * will become 'varchar(255) not null'.
     * @return string the SQL statement for changing the definition of a column.
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function alter_column($table, $column, $type)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * Builds a SQL statement for adding a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint.
     * @param string $table the table that the primary key constraint will be added to.
     * @param string|array $columns comma separated string or array of columns that the primary key will consist of.
     * @return string the SQL statement for adding a primary key constraint to an existing table.
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function add_primary_key($name, $table, $columns)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * Builds a SQL statement for removing a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     * @throws NotSupportedException this is not supported by SQLite
     */
    public function drop_primary_key($name, $table)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function add_unique($name, $table, $columns)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function drop_unique($name, $table)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function add_check($name, $table, $expression)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function drop_check($name, $table)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function add_default_value($name, $table, $column, $value)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by SQLite.
     */
    public function drop_default_value($name, $table)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException
     * @since 2.0.8
     */
    public function add_comment_on_column($table, $column, $comment)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException
     * @since 2.0.8
     */
    public function add_comment_on_table($table, $comment)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException
     * @since 2.0.8
     */
    public function drop_comment_from_column($table, $column)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException
     * @since 2.0.8
     */
    public function drop_comment_from_table($table)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by SQLite.');
    }
    /**
     * {@inheritdoc}
     */
    public function build_limit($limit, $offset)
    {
        $sql = '';
        if ($this->has_limit($limit)) {
            $sql = 'LIMIT ' . $limit;
            if ($this->has_offset($offset)) {
                $sql .= ' OFFSET ' . $offset;
            }
        } elseif ($this->has_offset($offset)) {
            // limit is not optional in SQLite
            // https://www.sqlite.org/syntaxdiagrams.html#select-stmt
            $sql = "LIMIT 9223372036854775807 OFFSET {$offset}";
            // 2^63-1
        }
        return $sql;
    }
    /**
     * {@inheritdoc}
     */
    public function build($query, $params = [])
    {
        $query = $query->prepare($this);
        $params = empty($params) ? $query->params : array_merge($params, $query->params);
        $clauses = [$this->build_select($query->select, $params, $query->distinct, $query->select_option), $this->build_from($query->from, $params), $this->build_join($query->join, $params), $this->build_where($query->where, $params), $this->build_group_by($query->group_by), $this->build_having($query->having, $params)];
        $sql = implode($this->separator, array_filter($clauses));
        $sql = $this->build_order_by_and_limit($sql, $query->order_by, $query->limit, $query->offset);
        if (!empty($query->order_by)) {
            foreach ($query->order_by as $expression) {
                if ($expression instanceof Expression_Interface) {
                    $this->build_expression($expression, $params);
                }
            }
        }
        if (!empty($query->group_by)) {
            foreach ($query->group_by as $expression) {
                if ($expression instanceof Expression_Interface) {
                    $this->build_expression($expression, $params);
                }
            }
        }
        $union = $this->build_union($query->union, $params);
        if ($union !== '') {
            $sql = "{$sql}{$this->separator}{$union}";
        }
        $with = $this->build_with_queries($query->with_queries, $params);
        if ($with !== '') {
            $sql = "{$with}{$this->separator}{$sql}";
        }
        return [$sql, $params];
    }
    /**
     * {@inheritdoc}
     */
    public function build_union($unions, &$params)
    {
        if (empty($unions)) {
            return '';
        }
        $result = '';
        foreach ($unions as $i => $union) {
            $query = $union['query'];
            if ($query instanceof Query) {
                list($unions[$i]['query'], $params) = $this->build($query, $params);
            }
            $result .= ' UNION ' . ($union['all'] ? 'ALL ' : '') . ' ' . $unions[$i]['query'];
        }
        return trim($result);
    }
    /**
     * {@inheritdoc}
     */
    public function create_index($name, $table, $columns, $unique = false)
    {
        $table_parts = explode('.', $table);
        $schema = null;
        if (count($table_parts) === 2) {
            list($schema, $table) = $table_parts;
        }
        return ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ') . $this->db->quote_table_name(($schema ? $schema . '.' : '') . $name) . ' ON ' . $this->db->quote_table_name($table) . ' (' . $this->build_columns($columns) . ')';
    }
}