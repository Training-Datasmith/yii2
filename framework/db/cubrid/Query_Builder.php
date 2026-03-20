<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\cubrid;

use yii\base\InvalidArgumentException;
use yii\base\Not_Supported_Exception;
use yii\db\Exception;
use yii\db\Expression;
/**
 * QueryBuilder is the query builder for CUBRID databases (version 9.3.x and higher).
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class Query_Builder extends \yii\db\Query_Builder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $type_map = [Schema::TYPE_PK => 'int NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_UPK => 'int UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_BIGPK => 'bigint NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_UBIGPK => 'bigint UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_CHAR => 'char(1)', Schema::TYPE_STRING => 'varchar(255)', Schema::TYPE_TEXT => 'varchar', Schema::TYPE_TINYINT => 'smallint', Schema::TYPE_SMALLINT => 'smallint', Schema::TYPE_INTEGER => 'int', Schema::TYPE_BIGINT => 'bigint', Schema::TYPE_FLOAT => 'float(7)', Schema::TYPE_DOUBLE => 'double(15)', Schema::TYPE_DECIMAL => 'decimal(10,0)', Schema::TYPE_DATETIME => 'datetime', Schema::TYPE_TIMESTAMP => 'timestamp', Schema::TYPE_TIME => 'time', Schema::TYPE_DATE => 'date', Schema::TYPE_BINARY => 'blob', Schema::TYPE_BOOLEAN => 'smallint', Schema::TYPE_MONEY => 'decimal(19,4)'];
    /**
     * {@inheritdoc}
     */
    protected function default_expression_builders()
    {
        return array_merge(parent::default_expression_builders(), ['yii\db\conditions\LikeCondition' => 'yii\db\cubrid\conditions\LikeConditionBuilder']);
    }
    /**
     * {@inheritdoc}
     * @see https://www.cubrid.org/manual/en/9.3.0/sql/query/merge.html
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
        $merge_sql = 'MERGE INTO ' . $this->db->quote_table_name($table) . ' ' . 'USING (' . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')' : ltrim($values, ' ')) . ') AS "EXCLUDED" (' . implode(', ', $insert_names) . ') ' . "ON ({$on})";
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
        $table = $this->db->get_table_schema($table_name);
        if ($table !== null && $table->sequence_name !== null) {
            $table_name = $this->db->quote_table_name($table_name);
            if ($value === null) {
                $key = reset($table->primary_key);
                $value = (int) $this->db->create_command("SELECT MAX(`{$key}`) FROM " . $this->db->schema->quote_table_name($table_name))->query_scalar() + 1;
            } else {
                $value = (int) $value;
            }
            return 'ALTER TABLE ' . $this->db->schema->quote_table_name($table_name) . " AUTO_INCREMENT={$value};";
        } elseif ($table === null) {
            throw new InvalidArgumentException("Table not found: {$table_name}");
        }
        throw new InvalidArgumentException("There is not sequence associated with table '{$table_name}'.");
    }
    /**
     * {@inheritdoc}
     */
    public function build_limit($limit, $offset)
    {
        $sql = '';
        // limit is not optional in CUBRID
        // https://www.cubrid.org/manual/en/9.3.0/sql/query/select.html#limit-clause
        // "You can specify a very big integer for row_count to display to the last row, starting from a specific row."
        if ($this->has_limit($limit)) {
            $sql = 'LIMIT ' . $limit;
            if ($this->has_offset($offset)) {
                $sql .= ' OFFSET ' . $offset;
            }
        } elseif ($this->has_offset($offset)) {
            $sql = "LIMIT 9223372036854775807 OFFSET {$offset}";
            // 2^63-1
        }
        return $sql;
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function select_exists($raw_sql)
    {
        return 'SELECT CASE WHEN EXISTS(' . $raw_sql . ') THEN 1 ELSE 0 END';
    }
    /**
     * {@inheritdoc}
     * @see https://www.cubrid.org/manual/en/9.3.0/sql/schema/table.html#drop-index-clause
     */
    public function drop_index($name, $table)
    {
        /** @var Schema $schema */
        $schema = $this->db->get_schema();
        foreach ($schema->get_table_uniques($table) as $unique) {
            if ($unique->name === $name) {
                return $this->drop_unique($name, $table);
            }
        }
        return 'DROP INDEX ' . $this->db->quote_table_name($name) . ' ON ' . $this->db->quote_table_name($table);
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by CUBRID.
     */
    public function add_check($name, $table, $expression)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by CUBRID.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException this is not supported by CUBRID.
     */
    public function drop_check($name, $table)
    {
        throw new Not_Supported_Exception(__METHOD__ . ' is not supported by CUBRID.');
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function add_comment_on_column($table, $column, $comment)
    {
        $definition = $this->get_column_definition($table, $column);
        $definition = trim(preg_replace("/COMMENT '(.*?)'/i", '', $definition));
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' CHANGE ' . $this->db->quote_column_name($column) . ' ' . $this->db->quote_column_name($column) . (empty($definition) ? '' : ' ' . $definition) . ' COMMENT ' . $this->db->quote_value($comment);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function add_comment_on_table($table, $comment)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' COMMENT ' . $this->db->quote_value($comment);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function drop_comment_from_column($table, $column)
    {
        return $this->add_comment_on_column($table, $column, '');
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function drop_comment_from_table($table)
    {
        return $this->add_comment_on_table($table, '');
    }
    /**
     * Gets column definition.
     *
     * @param string $table table name
     * @param string $column column name
     * @return string|null the column definition
     * @throws Exception in case when table does not contain column
     * @since 2.0.8
     */
    private function get_column_definition($table, $column)
    {
        $row = $this->db->create_command('SHOW CREATE TABLE ' . $this->db->quote_table_name($table))->query_one();
        if ($row === false) {
            throw new Exception("Unable to find column '{$column}' in table '{$table}'.");
        }
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }
        $sql = preg_replace('/^[^(]+\((.*)\).*$/', '\1', $sql);
        $sql = str_replace(', [', ",\n[", $sql);
        if (preg_match_all('/^\s*\[(.*?)\]\s+(.*?),?$/m', $sql, $matches)) {
            foreach ($matches[1] as $i => $c) {
                if ($c === $column) {
                    return $matches[2][$i];
                }
            }
        }
        return null;
    }
}