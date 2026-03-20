<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\pgsql;

use yii\base\InvalidArgumentException;
use yii\db\Expression;
use yii\db\Expression_Interface;
use yii\db\Pdo_Value;
use yii\db\Query;
use yii\helpers\String_Helper;
/**
 * QueryBuilder is the query builder for PostgreSQL databases.
 *
 * @author Gevik Babakhani <gevikb@gmail.com>
 * @since 2.0
 */
class Query_Builder extends \yii\db\Query_Builder
{
    /**
     * Defines a UNIQUE index for [[createIndex()]].
     * @since 2.0.6
     */
    public const INDEX_UNIQUE = 'unique';
    /**
     * Defines a B-tree index for [[createIndex()]].
     * @since 2.0.6
     */
    public const INDEX_B_TREE = 'btree';
    /**
     * Defines a hash index for [[createIndex()]].
     * @since 2.0.6
     */
    public const INDEX_HASH = 'hash';
    /**
     * Defines a GiST index for [[createIndex()]].
     * @since 2.0.6
     */
    public const INDEX_GIST = 'gist';
    /**
     * Defines a GIN index for [[createIndex()]].
     * @since 2.0.6
     */
    public const INDEX_GIN = 'gin';
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $type_map = [Schema::TYPE_PK => 'serial NOT NULL PRIMARY KEY', Schema::TYPE_UPK => 'serial NOT NULL PRIMARY KEY', Schema::TYPE_BIGPK => 'bigserial NOT NULL PRIMARY KEY', Schema::TYPE_UBIGPK => 'bigserial NOT NULL PRIMARY KEY', Schema::TYPE_CHAR => 'char(1)', Schema::TYPE_STRING => 'varchar(255)', Schema::TYPE_TEXT => 'text', Schema::TYPE_TINYINT => 'smallint', Schema::TYPE_SMALLINT => 'smallint', Schema::TYPE_INTEGER => 'integer', Schema::TYPE_BIGINT => 'bigint', Schema::TYPE_FLOAT => 'double precision', Schema::TYPE_DOUBLE => 'double precision', Schema::TYPE_DECIMAL => 'numeric(10,0)', Schema::TYPE_DATETIME => 'timestamp(0)', Schema::TYPE_TIMESTAMP => 'timestamp(0)', Schema::TYPE_TIME => 'time(0)', Schema::TYPE_DATE => 'date', Schema::TYPE_BINARY => 'bytea', Schema::TYPE_BOOLEAN => 'boolean', Schema::TYPE_MONEY => 'numeric(19,4)', Schema::TYPE_JSON => 'jsonb'];
    /**
     * {@inheritdoc}
     */
    protected function default_condition_classes()
    {
        return array_merge(parent::default_condition_classes(), ['ILIKE' => 'yii\db\conditions\LikeCondition', 'NOT ILIKE' => 'yii\db\conditions\LikeCondition', 'OR ILIKE' => 'yii\db\conditions\LikeCondition', 'OR NOT ILIKE' => 'yii\db\conditions\LikeCondition']);
    }
    /**
     * {@inheritdoc}
     */
    protected function default_expression_builders()
    {
        return array_merge(parent::default_expression_builders(), ['yii\db\ArrayExpression' => 'yii\db\pgsql\ArrayExpressionBuilder', 'yii\db\JsonExpression' => 'yii\db\pgsql\JsonExpressionBuilder']);
    }
    /**
     * Builds a SQL statement for creating a new index.
     * @param string $name the name of the index. The name will be properly quoted by the method.
     * @param string $table the table that the new index will be created for. The table name will be properly quoted by the method.
     * @param string|array $columns the column(s) that should be included in the index. If there are multiple columns,
     * separate them with commas or use an array to represent them. Each column name will be properly quoted
     * by the method, unless a parenthesis is found in the name.
     * @param bool|string $unique whether to make this a UNIQUE index constraint. You can pass `true` or [[INDEX_UNIQUE]] to create
     * a unique index, `false` to make a non-unique index using the default index type, or one of the following constants to specify
     * the index method to use: [[INDEX_B_TREE]], [[INDEX_HASH]], [[INDEX_GIST]], [[INDEX_GIN]].
     * @return string the SQL statement for creating a new index.
     * @see https://www.postgresql.org/docs/8.2/sql-createindex.html
     */
    public function create_index($name, $table, $columns, $unique = false)
    {
        if ($unique === self::INDEX_UNIQUE || $unique === true) {
            $index = false;
            $unique = true;
        } else {
            $index = $unique;
            $unique = false;
        }
        return ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ') . $this->db->quote_table_name($name) . ' ON ' . $this->db->quote_table_name($table) . ($index !== false ? " USING {$index}" : '') . ' (' . $this->build_columns($columns) . ')';
    }
    /**
     * Builds a SQL statement for dropping an index.
     * @param string $name the name of the index to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose index is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping an index.
     */
    public function drop_index($name, $table)
    {
        if (strpos($table, '.') !== false && strpos($name, '.') === false) {
            if (strpos($table, '{{') !== false) {
                $table = preg_replace('/\{\{(.*?)\}\}/', '\1', $table);
                list($schema, $table) = explode('.', $table);
                if (strpos($schema, '%') === false) {
                    $name = $schema . '.' . $name;
                } else {
                    $name = '{{' . $schema . '.' . $name . '}}';
                }
            } else {
                list($schema) = explode('.', $table);
                $name = $schema . '.' . $name;
            }
        }
        return 'DROP INDEX ' . $this->db->quote_table_name($name);
    }
    /**
     * Builds a SQL statement for renaming a DB table.
     * @param string $oldName the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB table.
     */
    public function rename_table($old_name, $new_name)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($old_name) . ' RENAME TO ' . $this->db->quote_table_name($new_name);
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
            // c.f. https://www.postgresql.org/docs/8.1/functions-sequence.html
            $sequence = $this->db->quote_table_name($table->sequence_name);
            $table_name = $this->db->quote_table_name($table_name);
            if ($value === null) {
                $key = $this->db->quote_column_name(reset($table->primary_key));
                $value = "(SELECT COALESCE(MAX({$key}),0) FROM {$table_name})+1";
            } else {
                $value = (int) $value;
            }
            return "SELECT SETVAL('{$sequence}',{$value},false)";
        } elseif ($table === null) {
            throw new InvalidArgumentException("Table not found: {$table_name}");
        }
        throw new InvalidArgumentException("There is not sequence associated with table '{$table_name}'.");
    }
    /**
     * Builds a SQL statement for enabling or disabling integrity check.
     * @param bool $check whether to turn on or off the integrity check.
     * @param string $schema the schema of the tables.
     * @param string $table the table name.
     * @return string the SQL statement for checking integrity
     */
    public function check_integrity($check = true, $schema = '', $table = '')
    {
        /** @var Schema $dbSchema */
        $db_schema = $this->db->get_schema();
        $enable = $check ? 'ENABLE' : 'DISABLE';
        $schema = $schema ?: $db_schema->default_schema;
        $table_names = $table ? [$table] : $db_schema->get_table_names($schema);
        $view_names = $db_schema->get_view_names($schema);
        $table_names = array_diff($table_names, $view_names);
        $command = '';
        foreach ($table_names as $table_name) {
            $table_name = $this->db->quote_table_name("{$schema}.{$table_name}");
            $command .= "ALTER TABLE {$table_name} {$enable} TRIGGER ALL; ";
        }
        // enable to have ability to alter several tables
        $this->db->get_master_pdo()->set_attribute(\PDO::ATTR_EMULATE_PREPARES, true);
        return $command;
    }
    /**
     * Builds a SQL statement for truncating a DB table.
     * Explicitly restarts identity for PGSQL to be consistent with other databases which all do this by default.
     * @param string $table the table to be truncated. The name will be properly quoted by the method.
     * @return string the SQL statement for truncating a DB table.
     */
    public function truncate_table($table)
    {
        return 'TRUNCATE TABLE ' . $this->db->quote_table_name($table) . ' RESTART IDENTITY';
    }
    /**
     * Builds a SQL statement for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[getColumnType()]] method will be invoked to convert abstract
     * column type (if any) into the physical one. Anything that is not recognized as abstract type will be kept
     * in the generated SQL. For example, 'string' will be turned into 'varchar(255)', while 'string not null'
     * will become 'varchar(255) not null'. You can also use PostgreSQL-specific syntax such as `SET NOT NULL`.
     * @return string the SQL statement for changing the definition of a column.
     */
    public function alter_column($table, $column, $type)
    {
        $column_name = $this->db->quote_column_name($column);
        $table_name = $this->db->quote_table_name($table);
        // https://github.com/yiisoft/yii2/issues/4492
        // https://www.postgresql.org/docs/9.1/sql-altertable.html
        if (preg_match('/^(DROP|SET|RESET)\s+/i', $type)) {
            return "ALTER TABLE {$table_name} ALTER COLUMN {$column_name} {$type}";
        }
        $type = 'TYPE ' . $this->get_column_type($type);
        $multi_alter_statement = [];
        $constraint_prefix = preg_replace('/[^a-z0-9_]/i', '', $table . '_' . $column);
        if (preg_match('/\s+DEFAULT\s+(["\']?\w*["\']?)/i', $type, $matches)) {
            $type = preg_replace('/\s+DEFAULT\s+(["\']?\w*["\']?)/i', '', $type);
            $multi_alter_statement[] = "ALTER COLUMN {$column_name} SET DEFAULT {$matches[1]}";
        } else {
            // safe to drop default even if there was none in the first place
            $multi_alter_statement[] = "ALTER COLUMN {$column_name} DROP DEFAULT";
        }
        $type = preg_replace('/\s+NOT\s+NULL/i', '', $type, -1, $count);
        if ($count) {
            $multi_alter_statement[] = "ALTER COLUMN {$column_name} SET NOT NULL";
        } else {
            // remove additional null if any
            $type = preg_replace('/\s+NULL/i', '', $type);
            // safe to drop not null even if there was none in the first place
            $multi_alter_statement[] = "ALTER COLUMN {$column_name} DROP NOT NULL";
        }
        if (preg_match('/\s+CHECK\s+\((.+)\)/i', $type, $matches)) {
            $type = preg_replace('/\s+CHECK\s+\((.+)\)/i', '', $type);
            $multi_alter_statement[] = "ADD CONSTRAINT {$constraint_prefix}_check CHECK ({$matches[1]})";
        }
        $type = preg_replace('/\s+UNIQUE/i', '', $type, -1, $count);
        if ($count) {
            $multi_alter_statement[] = "ADD UNIQUE ({$column_name})";
        }
        // add what's left at the beginning
        array_unshift($multi_alter_statement, "ALTER COLUMN {$column_name} {$type}");
        return 'ALTER TABLE ' . $table_name . ' ' . implode(', ', $multi_alter_statement);
    }
    /**
     * {@inheritdoc}
     */
    public function insert($table, $columns, &$params)
    {
        return parent::insert($table, $this->normalize_table_row_data($table, $columns), $params);
    }
    /**
     * {@inheritdoc}
     * @see https://www.postgresql.org/docs/9.5/static/sql-insert.html#SQL-ON-CONFLICT
     * @see https://stackoverflow.com/questions/1109061/insert-on-duplicate-update-in-postgresql/8702291#8702291
     */
    public function upsert($table, $insert_columns, $update_columns, &$params)
    {
        $insert_columns = $this->normalize_table_row_data($table, $insert_columns);
        if (!is_bool($update_columns)) {
            $update_columns = $this->normalize_table_row_data($table, $update_columns);
        }
        if (version_compare($this->db->get_server_version(), '9.5', '<')) {
            return $this->old_upsert($table, $insert_columns, $update_columns, $params);
        }
        return $this->new_upsert($table, $insert_columns, $update_columns, $params);
    }
    /**
     * [[upsert()]] implementation for PostgreSQL 9.5 or higher.
     * @param string $table
     * @param array|Query $insertColumns
     * @param array|bool $updateColumns
     * @param array $params
     * @return string
     */
    private function new_upsert($table, $insert_columns, $update_columns, &$params)
    {
        $insert_sql = $this->insert($table, $insert_columns, $params);
        list($unique_names, , $update_names) = $this->prepare_upsert_columns($table, $insert_columns, $update_columns);
        if (empty($unique_names)) {
            return $insert_sql;
        }
        if ($update_names === []) {
            // there are no columns to update
            $update_columns = false;
        }
        if ($update_columns === false) {
            return "{$insert_sql} ON CONFLICT DO NOTHING";
        }
        if ($update_columns === true) {
            $update_columns = [];
            foreach ($update_names as $name) {
                $update_columns[$name] = new Expression('EXCLUDED.' . $this->db->quote_column_name($name));
            }
        }
        list($updates, $params) = $this->prepare_update_sets($table, $update_columns, $params);
        return $insert_sql . ' ON CONFLICT (' . implode(', ', $unique_names) . ') DO UPDATE SET ' . implode(', ', $updates);
    }
    /**
     * [[upsert()]] implementation for PostgreSQL older than 9.5.
     * @param string $table
     * @param array|Query $insertColumns
     * @param array|bool $updateColumns
     * @param array $params
     * @return string
     */
    private function old_upsert($table, $insert_columns, $update_columns, &$params)
    {
        list($unique_names, $insert_names, $update_names) = $this->prepare_upsert_columns($table, $insert_columns, $update_columns, $constraints);
        if (empty($unique_names)) {
            return $this->insert($table, $insert_columns, $params);
        }
        if ($update_names === []) {
            // there are no columns to update
            $update_columns = false;
        }
        /** @var Schema $schema */
        $schema = $this->db->get_schema();
        if (!$insert_columns instanceof Query) {
            $table_schema = $schema->get_table_schema($table);
            $column_schemas = $table_schema !== null ? $table_schema->columns : [];
            foreach ($insert_columns as $name => $value) {
                // NULLs and numeric values must be type hinted in order to be used in SET assigments
                // NVM, let's cast them all
                if (isset($column_schemas[$name])) {
                    $ph_name = self::PARAM_PREFIX . count($params);
                    $params[$ph_name] = $value;
                    $insert_columns[$name] = new Expression("CAST({$ph_name} AS {$column_schemas[$name]->db_type})");
                }
            }
        }
        list(, $placeholders, $values, $params) = $this->prepare_insert_values($table, $insert_columns, $params);
        $update_condition = ['or'];
        $insert_condition = ['or'];
        $quoted_table_name = $schema->quote_table_name($table);
        foreach ($constraints as $constraint) {
            $constraint_update_condition = ['and'];
            $constraint_insert_condition = ['and'];
            foreach ($constraint->column_names as $name) {
                $quoted_name = $schema->quote_column_name($name);
                $constraint_update_condition[] = "{$quoted_table_name}.{$quoted_name}=\"EXCLUDED\".{$quoted_name}";
                $constraint_insert_condition[] = "\"upsert\".{$quoted_name}=\"EXCLUDED\".{$quoted_name}";
            }
            $update_condition[] = $constraint_update_condition;
            $insert_condition[] = $constraint_insert_condition;
        }
        $with_sql = 'WITH "EXCLUDED" (' . implode(', ', $insert_names) . ') AS (' . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')' : ltrim($values, ' ')) . ')';
        if ($update_columns === false) {
            $select_sub_query = (new Query())->select(new Expression('1'))->from($table)->where($update_condition);
            $insert_select_sub_query = (new Query())->select($insert_names)->from('EXCLUDED')->where(['not exists', $select_sub_query]);
            $insert_sql = $this->insert($table, $insert_select_sub_query, $params);
            return "{$with_sql} {$insert_sql}";
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
        $update_sql = 'UPDATE ' . $this->db->quote_table_name($table) . ' SET ' . implode(', ', $updates) . ' FROM "EXCLUDED" ' . $this->build_where($update_condition, $params) . ' RETURNING ' . $this->db->quote_table_name($table) . '.*';
        $select_upsert_sub_query = (new Query())->select(new Expression('1'))->from('upsert')->where($insert_condition);
        $insert_select_sub_query = (new Query())->select($insert_names)->from('EXCLUDED')->where(['not exists', $select_upsert_sub_query]);
        $insert_sql = $this->insert($table, $insert_select_sub_query, $params);
        return "{$with_sql}, \"upsert\" AS ({$update_sql}) {$insert_sql}";
    }
    /**
     * {@inheritdoc}
     */
    public function update($table, $columns, $condition, &$params)
    {
        return parent::update($table, $this->normalize_table_row_data($table, $columns), $condition, $params);
    }
    /**
     * Normalizes data to be saved into the table, performing extra preparations and type converting, if necessary.
     *
     * @param string $table the table that data will be saved into.
     * @param array|Query $columns the column data (name => value) to be saved into the table or instance
     * of [[yii\db\Query|Query]] to perform INSERT INTO ... SELECT SQL statement.
     * Passing of [[yii\db\Query|Query]] is available since version 2.0.11.
     * @return array|Query normalized columns
     * @since 2.0.9
     */
    private function normalize_table_row_data($table, $columns)
    {
        if ($columns instanceof Query) {
            return $columns;
        }
        if (($table_schema = $this->db->get_schema()->get_table_schema($table)) !== null) {
            $column_schemas = $table_schema->columns;
            foreach ($columns as $name => $value) {
                if (isset($column_schemas[$name]) && $column_schemas[$name]->type === Schema::TYPE_BINARY && is_string($value)) {
                    $columns[$name] = new Pdo_Value($value, \PDO::PARAM_LOB);
                    // explicitly setup PDO param type for binary column
                }
            }
        }
        return $columns;
    }
    /**
     * {@inheritdoc}
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
                } elseif ($value === true) {
                    $value = 'TRUE';
                } elseif ($value === false) {
                    $value = 'FALSE';
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
        return 'INSERT INTO ' . $schema->quote_table_name($table) . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $values);
    }
}