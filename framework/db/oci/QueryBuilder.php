<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\oci;

use yii\base\InvalidArgumentException;
use yii\db\Connection;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Expression_Interface;
use yii\db\Query;
use yii\helpers\String_Helper;
/**
 * QueryBuilder is the query builder for Oracle databases.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Query_Builder extends \yii\db\Query_Builder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $type_map = [Schema::TYPE_PK => 'NUMBER(10) NOT NULL PRIMARY KEY', Schema::TYPE_UPK => 'NUMBER(10) UNSIGNED NOT NULL PRIMARY KEY', Schema::TYPE_BIGPK => 'NUMBER(20) NOT NULL PRIMARY KEY', Schema::TYPE_UBIGPK => 'NUMBER(20) UNSIGNED NOT NULL PRIMARY KEY', Schema::TYPE_CHAR => 'CHAR(1)', Schema::TYPE_STRING => 'VARCHAR2(255)', Schema::TYPE_TEXT => 'CLOB', Schema::TYPE_TINYINT => 'NUMBER(3)', Schema::TYPE_SMALLINT => 'NUMBER(5)', Schema::TYPE_INTEGER => 'NUMBER(10)', Schema::TYPE_BIGINT => 'NUMBER(20)', Schema::TYPE_FLOAT => 'NUMBER', Schema::TYPE_DOUBLE => 'NUMBER', Schema::TYPE_DECIMAL => 'NUMBER', Schema::TYPE_DATETIME => 'TIMESTAMP', Schema::TYPE_TIMESTAMP => 'TIMESTAMP', Schema::TYPE_TIME => 'TIMESTAMP', Schema::TYPE_DATE => 'DATE', Schema::TYPE_BINARY => 'BLOB', Schema::TYPE_BOOLEAN => 'NUMBER(1)', Schema::TYPE_MONEY => 'NUMBER(19,4)'];
    /**
     * {@inheritdoc}
     */
    protected function default_expression_builders()
    {
        return array_merge(parent::default_expression_builders(), ['yii\db\conditions\InCondition' => 'yii\db\oci\conditions\InConditionBuilder', 'yii\db\conditions\LikeCondition' => 'yii\db\oci\conditions\LikeConditionBuilder']);
    }
    /**
     * {@inheritdoc}
     */
    public function build_order_by_and_limit($sql, $order_by, $limit, $offset)
    {
        $order_by = $this->build_order_by($order_by);
        if ($order_by !== '') {
            $sql .= $this->separator . $order_by;
        }
        $filters = [];
        if ($this->has_offset($offset)) {
            $filters[] = 'rowNumId > ' . $offset;
        }
        if ($this->has_limit($limit)) {
            $filters[] = 'rownum <= ' . $limit;
        }
        if (empty($filters)) {
            return $sql;
        }
        $filter = implode(' AND ', $filters);
        return <<<EOD
        WITH USER_SQL AS ({$sql}),
            PAGINATION AS (SELECT USER_SQL.*, rownum as rowNumId FROM USER_SQL)
        SELECT *
        FROM PAGINATION
        WHERE {$filter}
        EOD;
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
     *
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[getColumnType]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     * @return string the SQL statement for changing the definition of a column.
     */
    public function alter_column($table, $column, $type)
    {
        $type = $this->get_column_type($type);
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' MODIFY ' . $this->db->quote_column_name($column) . ' ' . $this->get_column_type($type);
    }
    /**
     * Builds a SQL statement for dropping an index.
     *
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping an index.
     */
    public function drop_index($name, $table)
    {
        return 'DROP INDEX ' . $this->db->quote_table_name($name);
    }
    /**
     * {@inheritdoc}
     */
    public function execute_reset_sequence($table, $value = null)
    {
        $table_schema = $this->db->get_table_schema($table);
        if ($table_schema === null) {
            throw new InvalidArgumentException("Unknown table: {$table}");
        }
        if ($table_schema->sequence_name === null) {
            throw new InvalidArgumentException("There is no sequence associated with table: {$table}");
        }
        if ($value !== null) {
            $value = (int) $value;
        } else {
            if (count($table_schema->primary_key) > 1) {
                throw new InvalidArgumentException("Can't reset sequence for composite primary key in table: {$table}");
            }
            // use master connection to get the biggest PK value
            $value = $this->db->use_master(function (Connection $db) use ($table_schema) {
                return $db->create_command('SELECT MAX("' . $table_schema->primary_key[0] . '") FROM "' . $table_schema->name . '"')->query_scalar();
            }) + 1;
        }
        //Oracle needs at least two queries to reset sequence (see adding transactions and/or use alter method to avoid grants' issue?)
        $this->db->create_command('DROP SEQUENCE "' . $table_schema->sequence_name . '"')->execute();
        $this->db->create_command('CREATE SEQUENCE "' . $table_schema->sequence_name . '" START WITH ' . $value . ' INCREMENT BY 1 NOMAXVALUE NOCACHE')->execute();
    }
    /**
     * {@inheritdoc}
     */
    public function add_foreign_key($name, $table, $columns, $ref_table, $ref_columns, $delete = null, $update = null)
    {
        $sql = 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' ADD CONSTRAINT ' . $this->db->quote_column_name($name) . ' FOREIGN KEY (' . $this->build_columns($columns) . ')' . ' REFERENCES ' . $this->db->quote_table_name($ref_table) . ' (' . $this->build_columns($ref_columns) . ')';
        if ($delete !== null) {
            $sql .= ' ON DELETE ' . $delete;
        }
        if ($update !== null) {
            throw new Exception('Oracle does not support ON UPDATE clause.');
        }
        return $sql;
    }
    /**
     * {@inheritdoc}
     */
    protected function prepare_insert_values($table, $columns, $params = [])
    {
        list($names, $placeholders, $values, $params) = parent::prepare_insert_values($table, $columns, $params);
        if (!$columns instanceof Query && empty($names)) {
            $table_schema = $this->db->get_schema()->get_table_schema($table);
            if ($table_schema !== null) {
                $columns = !empty($table_schema->primary_key) ? $table_schema->primary_key : [reset($table_schema->columns)->name];
                foreach ($columns as $name) {
                    $names[] = $this->db->quote_column_name($name);
                    $placeholders[] = 'DEFAULT';
                }
            }
        }
        return [$names, $placeholders, $values, $params];
    }
    /**
     * {@inheritdoc}
     * @see https://docs.oracle.com/cd/B28359_01/server.111/b28286/statements_9016.htm#SQLRF01606
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
        $on_condition = ['or'];
        $quoted_table_name = $this->db->quote_table_name($table);
        foreach ($constraints as $constraint) {
            $constraint_condition = ['and'];
            foreach ($constraint->column_names as $name) {
                $quoted_name = $this->db->quote_column_name($name);
                $constraint_condition[] = "{$quoted_table_name}.{$quoted_name}=\"EXCLUDED\".{$quoted_name}";
            }
            $on_condition[] = $constraint_condition;
        }
        $on = $this->build_condition($on_condition, $params);
        list(, $placeholders, $values, $params) = $this->prepare_insert_values($table, $insert_columns, $params);
        if (!empty($placeholders)) {
            $using_select_values = [];
            foreach ($insert_names as $index => $name) {
                $using_select_values[$name] = new Expression($placeholders[$index]);
            }
            $using_sub_query = (new Query())->select($using_select_values)->from('DUAL');
            list($using_values, $params) = $this->build($using_sub_query, $params);
        }
        $merge_sql = 'MERGE INTO ' . $this->db->quote_table_name($table) . ' ' . 'USING (' . (isset($using_values) ? $using_values : ltrim($values, ' ')) . ') "EXCLUDED" ' . "ON ({$on})";
        $insert_values = [];
        foreach ($insert_names as $name) {
            $quoted_name = $this->db->quote_column_name($name);
            if (strrpos($quoted_name, '.') === false) {
                $quoted_name = '"EXCLUDED".' . $quoted_name;
            }
            $insert_values[] = $quoted_name;
        }
        $insert_sql = 'INSERT (' . implode(', ', $insert_names) . ')' . ' VALUES (' . implode(', ', $insert_values) . ')';
        if ($update_columns === false) {
            return "{$merge_sql} WHEN NOT MATCHED THEN {$insert_sql}";
        }
        if ($update_columns === true) {
            $update_columns = [];
            foreach ($update_names as $name) {
                $quoted_name = $this->db->quote_column_name($name);
                if (strrpos($quoted_name, '.') === false) {
                    $quoted_name = '"EXCLUDED".' . $quoted_name;
                }
                $update_columns[$name] = new Expression($quoted_name);
            }
        }
        list($updates, $params) = $this->prepare_update_sets($table, $update_columns, $params);
        $update_sql = 'UPDATE SET ' . implode(', ', $updates);
        return "{$merge_sql} WHEN MATCHED THEN {$update_sql} WHEN NOT MATCHED THEN {$insert_sql}";
    }
    /**
     * Generates a batch INSERT SQL statement.
     *
     * For example,
     *
     * ```
     * $sql = $queryBuilder->batchInsert('user', ['name', 'age'], [
     *     ['Tom', 30],
     *     ['Jane', 20],
     *     ['Linda', 25],
     * ]);
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
                if (isset($columns[$i], $column_schemas[$columns[$i]])) {
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
            $values[] = '(' . implode(', ', $vs) . ')';
        }
        if (empty($values)) {
            return '';
        }
        foreach ($columns as $i => $name) {
            $columns[$i] = $schema->quote_column_name($name);
        }
        $table_and_columns = ' INTO ' . $schema->quote_table_name($table) . ' (' . implode(', ', $columns) . ') VALUES ';
        return 'INSERT ALL ' . $table_and_columns . implode($table_and_columns, $values) . ' SELECT 1 FROM SYS.DUAL';
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function select_exists($raw_sql)
    {
        return 'SELECT CASE WHEN EXISTS(' . $raw_sql . ') THEN 1 ELSE 0 END FROM DUAL';
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function drop_comment_from_column($table, $column)
    {
        return 'COMMENT ON COLUMN ' . $this->db->quote_table_name($table) . '.' . $this->db->quote_column_name($column) . " IS ''";
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function drop_comment_from_table($table)
    {
        return 'COMMENT ON TABLE ' . $this->db->quote_table_name($table) . " IS ''";
    }
}