<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mssql;

use yii\base\InvalidArgumentException;
use yii\base\Not_Supported_Exception;
use yii\db\Expression;
use yii\db\Query;
use yii\db\Table_Schema;
/**
 * QueryBuilder is the query builder for MS SQL Server databases (version 2008 and above).
 *
 * @author Timur Ruziev <resurtm@gmail.com>
 * @since 2.0
 */
class Query_Builder extends \yii\db\Query_Builder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $type_map = [Schema::TYPE_PK => 'int IDENTITY PRIMARY KEY', Schema::TYPE_UPK => 'int IDENTITY PRIMARY KEY', Schema::TYPE_BIGPK => 'bigint IDENTITY PRIMARY KEY', Schema::TYPE_UBIGPK => 'bigint IDENTITY PRIMARY KEY', Schema::TYPE_CHAR => 'nchar(1)', Schema::TYPE_STRING => 'nvarchar(255)', Schema::TYPE_TEXT => 'nvarchar(max)', Schema::TYPE_TINYINT => 'tinyint', Schema::TYPE_SMALLINT => 'smallint', Schema::TYPE_INTEGER => 'int', Schema::TYPE_BIGINT => 'bigint', Schema::TYPE_FLOAT => 'float', Schema::TYPE_DOUBLE => 'float', Schema::TYPE_DECIMAL => 'decimal(18,0)', Schema::TYPE_DATETIME => 'datetime', Schema::TYPE_TIMESTAMP => 'datetime', Schema::TYPE_TIME => 'time', Schema::TYPE_DATE => 'date', Schema::TYPE_BINARY => 'varbinary(max)', Schema::TYPE_BOOLEAN => 'bit', Schema::TYPE_MONEY => 'decimal(19,4)'];
    /**
     * {@inheritdoc}
     */
    protected function default_expression_builders()
    {
        return array_merge(parent::default_expression_builders(), ['yii\db\conditions\InCondition' => 'yii\db\mssql\conditions\InConditionBuilder', 'yii\db\conditions\LikeCondition' => 'yii\db\mssql\conditions\LikeConditionBuilder']);
    }
    /**
     * {@inheritdoc}
     */
    public function build_order_by_and_limit($sql, $order_by, $limit, $offset)
    {
        if (!$this->has_offset($offset) && !$this->has_limit($limit)) {
            $order_by = $this->build_order_by($order_by);
            return $order_by === '' ? $sql : $sql . $this->separator . $order_by;
        }
        if (version_compare($this->db->get_schema()->get_server_version(), '11', '<')) {
            return $this->old_build_order_by_and_limit($sql, $order_by, $limit, $offset);
        }
        return $this->new_build_order_by_and_limit($sql, $order_by, $limit, $offset);
    }
    /**
     * Builds the ORDER BY/LIMIT/OFFSET clauses for SQL SERVER 2012 or newer.
     * @param string $sql the existing SQL (without ORDER BY/LIMIT/OFFSET)
     * @param array $orderBy the order by columns. See [[\yii\db\Query::orderBy]] for more details on how to specify this parameter.
     * @param int $limit the limit number. See [[\yii\db\Query::limit]] for more details.
     * @param int $offset the offset number. See [[\yii\db\Query::offset]] for more details.
     * @return string the SQL completed with ORDER BY/LIMIT/OFFSET (if any)
     */
    protected function new_build_order_by_and_limit($sql, $order_by, $limit, $offset)
    {
        $order_by = $this->build_order_by($order_by);
        if ($order_by === '') {
            // ORDER BY clause is required when FETCH and OFFSET are in the SQL
            $order_by = 'ORDER BY (SELECT NULL)';
        }
        $sql .= $this->separator . $order_by;
        // https://technet.microsoft.com/en-us/library/gg699618.aspx
        $offset = $this->has_offset($offset) ? $offset : '0';
        $sql .= $this->separator . "OFFSET {$offset} ROWS";
        if ($this->has_limit($limit)) {
            $sql .= $this->separator . "FETCH NEXT {$limit} ROWS ONLY";
        }
        return $sql;
    }
    /**
     * Builds the ORDER BY/LIMIT/OFFSET clauses for SQL SERVER 2005 to 2008.
     * @param string $sql the existing SQL (without ORDER BY/LIMIT/OFFSET)
     * @param array $orderBy the order by columns. See [[\yii\db\Query::orderBy]] for more details on how to specify this parameter.
     * @param int|Expression $limit the limit number. See [[\yii\db\Query::limit]] for more details.
     * @param int $offset the offset number. See [[\yii\db\Query::offset]] for more details.
     * @return string the SQL completed with ORDER BY/LIMIT/OFFSET (if any)
     */
    protected function old_build_order_by_and_limit($sql, $order_by, $limit, $offset)
    {
        $order_by = $this->build_order_by($order_by);
        if ($order_by === '') {
            // ROW_NUMBER() requires an ORDER BY clause
            $order_by = 'ORDER BY (SELECT NULL)';
        }
        $sql = preg_replace('/^([\s(])*SELECT(\s+DISTINCT)?(?!\s*TOP\s*\()/i', "\\1SELECT\\2 rowNum = ROW_NUMBER() over ({$order_by}),", $sql);
        if ($this->has_limit($limit)) {
            if ($limit instanceof Expression) {
                $limit = '(' . (string) $limit . ')';
            }
            $sql = "SELECT TOP {$limit} * FROM ({$sql}) sub";
        } else {
            $sql = "SELECT * FROM ({$sql}) sub";
        }
        if ($this->has_offset($offset)) {
            $sql .= $this->separator . "WHERE rowNum > {$offset}";
        }
        return $sql;
    }
    /**
     * Builds a SQL statement for renaming a DB table.
     * @param string $oldName the table to be renamed. The name will be properly quoted by the method.
     * @param string $newName the new table name. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB table.
     */
    public function rename_table($old_name, $new_name)
    {
        return 'sp_rename ' . $this->db->quote_table_name($old_name) . ', ' . $this->db->quote_table_name($new_name);
    }
    /**
     * Builds a SQL statement for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB column.
     */
    public function rename_column($table, $old_name, $new_name)
    {
        $table = $this->db->quote_table_name($table);
        $old_name = $this->db->quote_column_name($old_name);
        $new_name = $this->db->quote_column_name($new_name);
        return "sp_rename '{$table}.{$old_name}', {$new_name}, 'COLUMN'";
    }
    /**
     * Builds a SQL statement for changing the definition of a column.
     * @param string $table the table whose column is to be changed. The table name will be properly quoted by the method.
     * @param string $column the name of the column to be changed. The name will be properly quoted by the method.
     * @param string $type the new column type. The [[getColumnType]] method will be invoked to convert abstract column type (if any)
     * into the physical one. Anything that is not recognized as abstract type will be kept in the generated SQL.
     * For example, 'string' will be turned into 'varchar(255)', while 'string not null' will become 'varchar(255) not null'.
     * @return string the SQL statement for changing the definition of a column.
     * @throws NotSupportedException if this is not supported by the underlying DBMS.
     */
    public function alter_column($table, $column, $type)
    {
        $sql_after = [$this->drop_constraints_for_column($table, $column, 'D')];
        $column_name = $this->db->quote_column_name($column);
        $table_name = $this->db->quote_table_name($table);
        $constraint_base = preg_replace('/[^a-z0-9_]/i', '', $table . '_' . $column);
        if ($type instanceof \yii\db\mssql\Column_Schema_Builder) {
            $type->set_alter_column_format();
            $default_value = $type->get_default_value();
            if ($default_value !== null) {
                $sql_after[] = $this->add_default_value("DF_{$constraint_base}", $table, $column, $default_value instanceof Expression ? $default_value : new Expression($default_value));
            }
            $check_value = $type->get_check_value();
            if ($check_value !== null) {
                $sql_after[] = "ALTER TABLE {$table_name} ADD CONSTRAINT " . $this->db->quote_column_name("CK_{$constraint_base}") . ' CHECK (' . ($default_value instanceof Expression ? $check_value : new Expression($check_value)) . ')';
            }
            if ($type->is_unique()) {
                $sql_after[] = "ALTER TABLE {$table_name} ADD CONSTRAINT " . $this->db->quote_column_name("UQ_{$constraint_base}") . " UNIQUE ({$column_name})";
            }
        }
        return 'ALTER TABLE ' . $table_name . ' ALTER COLUMN ' . $column_name . ' ' . $this->get_column_type($type) . "\n" . implode("\n", $sql_after);
    }
    /**
     * {@inheritdoc}
     */
    public function add_default_value($name, $table, $column, $value)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' ADD CONSTRAINT ' . $this->db->quote_column_name($name) . ' DEFAULT ' . $this->db->quote_value($value) . ' FOR ' . $this->db->quote_column_name($column);
    }
    /**
     * {@inheritdoc}
     */
    public function drop_default_value($name, $table)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP CONSTRAINT ' . $this->db->quote_column_name($name);
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
            if ($value === null || $value === 1) {
                $key = $this->db->quote_column_name(reset($table->primary_key));
                $sub_sql = (new Query())->select('last_value')->from('sys.identity_columns')->where(['object_id' => new Expression("OBJECT_ID('{$table_name}')")])->and_where(['IS NOT', 'last_value', null])->create_command($this->db)->get_raw_sql();
                $sql = "SELECT COALESCE(MAX({$key}), CASE WHEN EXISTS({$sub_sql}) THEN 0 ELSE 1 END) FROM {$table_name}";
                $value = $this->db->create_command($sql)->query_scalar();
            } else {
                $value = (int) $value;
            }
            return "DBCC CHECKIDENT ('{$table_name}', RESEED, {$value})";
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
        $enable = $check ? 'CHECK' : 'NOCHECK';
        $schema = $schema ?: $db_schema->default_schema;
        $table_names = $this->db->get_table_schema($table) ? [$table] : $db_schema->get_table_names($schema);
        $view_names = $db_schema->get_view_names($schema);
        $table_names = array_diff($table_names, $view_names);
        $command = '';
        foreach ($table_names as $table_name) {
            $table_name = $this->db->quote_table_name("{$schema}.{$table_name}");
            $command .= "ALTER TABLE {$table_name} {$enable} CONSTRAINT ALL; ";
        }
        return $command;
    }
    /**
     * Builds a SQL command for adding or updating a comment to a table or a column. The command built will check if a comment
     * already exists. If so, it will be updated, otherwise, it will be added.
     *
     * @param string $comment the text of the comment to be added. The comment will be properly quoted by the method.
     * @param string $table the table to be commented or whose column is to be commented. The table name will be
     * properly quoted by the method.
     * @param string|null $column optional. The name of the column to be commented. If empty, the command will add the
     * comment to the table instead. The column name will be properly quoted by the method.
     * @return string the SQL statement for adding a comment.
     * @throws InvalidArgumentException if the table does not exist.
     * @since 2.0.24
     */
    protected function build_add_comment_sql($comment, $table, $column = null)
    {
        $table_schema = $this->db->schema->get_table_schema($table);
        if ($table_schema === null) {
            throw new InvalidArgumentException("Table not found: {$table}");
        }
        $schema_name = $table_schema->schema_name ? "N'" . $table_schema->schema_name . "'" : 'SCHEMA_NAME()';
        $table_name = 'N' . $this->db->quote_value($table_schema->name);
        $column_name = $column ? 'N' . $this->db->quote_value($column) : null;
        $comment = 'N' . $this->db->quote_value($comment);
        $function_params = "\n            @name = N'MS_description',\n            @value = {$comment},\n            @level0type = N'SCHEMA', @level0name = {$schema_name},\n            @level1type = N'TABLE', @level1name = {$table_name}" . ($column ? ", @level2type = N'COLUMN', @level2name = {$column_name}" : '') . ';';
        return "\n            IF NOT EXISTS (\n                    SELECT 1\n                    FROM fn_listextendedproperty (\n                        N'MS_description',\n                        'SCHEMA', {$schema_name},\n                        'TABLE', {$table_name},\n                        " . ($column ? "'COLUMN', {$column_name} " : ' DEFAULT, DEFAULT ') . "\n                    )\n            )\n                EXEC sys.sp_addextendedproperty {$function_params}\n            ELSE\n                EXEC sys.sp_updateextendedproperty {$function_params}\n        ";
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function add_comment_on_column($table, $column, $comment)
    {
        return $this->build_add_comment_sql($comment, $table, $column);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function add_comment_on_table($table, $comment)
    {
        return $this->build_add_comment_sql($comment, $table);
    }
    /**
     * Builds a SQL command for removing a comment from a table or a column. The command built will check if a comment
     * already exists before trying to perform the removal.
     *
     * @param string $table the table that will have the comment removed or whose column will have the comment removed.
     * The table name will be properly quoted by the method.
     * @param string|null $column optional. The name of the column whose comment will be removed. If empty, the command
     * will remove the comment from the table instead. The column name will be properly quoted by the method.
     * @return string the SQL statement for removing the comment.
     * @throws InvalidArgumentException if the table does not exist.
     * @since 2.0.24
     */
    protected function build_remove_comment_sql($table, $column = null)
    {
        $table_schema = $this->db->schema->get_table_schema($table);
        if ($table_schema === null) {
            throw new InvalidArgumentException("Table not found: {$table}");
        }
        $schema_name = $table_schema->schema_name ? "N'" . $table_schema->schema_name . "'" : 'SCHEMA_NAME()';
        $table_name = 'N' . $this->db->quote_value($table_schema->name);
        $column_name = $column ? 'N' . $this->db->quote_value($column) : null;
        return "\n            IF EXISTS (\n                    SELECT 1\n                    FROM fn_listextendedproperty (\n                        N'MS_description',\n                        'SCHEMA', {$schema_name},\n                        'TABLE', {$table_name},\n                        " . ($column ? "'COLUMN', {$column_name} " : ' DEFAULT, DEFAULT ') . "\n                    )\n            )\n                EXEC sys.sp_dropextendedproperty\n                    @name = N'MS_description',\n                    @level0type = N'SCHEMA', @level0name = {$schema_name},\n                    @level1type = N'TABLE', @level1name = {$table_name}" . ($column ? ", @level2type = N'COLUMN', @level2name = {$column_name}" : '') . ';';
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function drop_comment_from_column($table, $column)
    {
        return $this->build_remove_comment_sql($table, $column);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function drop_comment_from_table($table)
    {
        return $this->build_remove_comment_sql($table);
    }
    /**
     * Returns an array of column names given model name.
     *
     * @param string|null $modelClass name of the model class
     * @return array|null array of column names
     */
    protected function get_all_column_names($model_class = null)
    {
        if (!$model_class) {
            return null;
        }
        /** @var \yii\db\ActiveRecord $modelClass */
        $schema = $model_class::get_table_schema();
        return array_keys($schema->columns);
    }
    /**
     * @return bool whether the version of the MSSQL being used is older than 2012.
     * @throws \yii\base\InvalidConfigException
     * @throws \yii\db\Exception
     * @deprecated 2.0.14 Use [[Schema::getServerVersion]] with [[\version_compare()]].
     */
    protected function is_old_mssql()
    {
        return version_compare($this->db->get_schema()->get_server_version(), '11', '<');
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
     * Normalizes data to be saved into the table, performing extra preparations and type converting, if necessary.
     * @param string $table the table that data will be saved into.
     * @param array $columns the column data (name => value) to be saved into the table.
     * @return array normalized columns
     */
    private function normalize_table_row_data($table, $columns, &$params)
    {
        if (($table_schema = $this->db->get_schema()->get_table_schema($table)) !== null) {
            $column_schemas = $table_schema->columns;
            foreach ($columns as $name => $value) {
                // @see https://github.com/yiisoft/yii2/issues/12599
                if (isset($column_schemas[$name]) && $column_schemas[$name]->type === Schema::TYPE_BINARY && $column_schemas[$name]->db_type === 'varbinary' && is_string($value)) {
                    // @see https://github.com/yiisoft/yii2/issues/12599
                    $columns[$name] = new Expression('CONVERT(VARBINARY(MAX), ' . ('0x' . bin2hex($value)) . ')');
                }
            }
        }
        return $columns;
    }
    /**
     * {@inheritdoc}
     * Added OUTPUT construction for getting inserted data (for SQL Server 2005 or later)
     * OUTPUT clause - The OUTPUT clause is new to SQL Server 2005 and has the ability to access
     * the INSERTED and DELETED tables as is the case with a trigger.
     */
    public function insert($table, $columns, &$params)
    {
        $columns = $this->normalize_table_row_data($table, $columns, $params);
        $version2005or_later = version_compare($this->db->get_schema()->get_server_version(), '9', '>=');
        list($names, $placeholders, $values, $params) = $this->prepare_insert_values($table, $columns, $params);
        $cols = [];
        $output_columns = [];
        if ($version2005or_later) {
            /** @var TableSchema $schema */
            $schema = $this->db->get_table_schema($table);
            foreach ($schema->columns as $column) {
                if ($column->is_computed) {
                    continue;
                }
                $db_type = $column->db_type;
                if (in_array($db_type, ['varchar', 'nvarchar', 'binary', 'varbinary'])) {
                    $db_type .= '(MAX)';
                } elseif (in_array($db_type, ['char', 'nchar'])) {
                    $db_type .= "({$column->size})";
                }
                if ($column->db_type === Schema::TYPE_TIMESTAMP) {
                    $db_type = $column->allow_null ? 'varbinary(8)' : 'binary(8)';
                }
                $quote_column_name = $this->db->quote_column_name($column->name);
                $cols[] = $quote_column_name . ' ' . $db_type . ' ' . ($column->allow_null ? 'NULL' : '');
                $output_columns[] = 'INSERTED.' . $quote_column_name;
            }
        }
        $count_columns = count($output_columns);
        $sql = 'INSERT INTO ' . $this->db->quote_table_name($table) . (!empty($names) ? ' (' . implode(', ', $names) . ')' : '') . ($version2005or_later && $count_columns ? ' OUTPUT ' . implode(',', $output_columns) . ' INTO @temporary_inserted' : '') . (!empty($placeholders) ? ' VALUES (' . implode(', ', $placeholders) . ')' : $values);
        if ($version2005or_later && $count_columns) {
            $sql = 'SET NOCOUNT ON;DECLARE @temporary_inserted TABLE (' . implode(', ', $cols) . ');' . $sql . ';SELECT * FROM @temporary_inserted';
        }
        return $sql;
    }
    /**
     * {@inheritdoc}
     * @see https://docs.microsoft.com/en-us/sql/t-sql/statements/merge-transact-sql
     * @see https://weblogs.sqlteam.com/dang/2009/01/31/upsert-race-condition-with-merge/
     */
    public function upsert($table, $insert_columns, $update_columns, &$params)
    {
        $insert_columns = $this->normalize_table_row_data($table, $insert_columns, $params);
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
                $constraint_condition[] = "{$quoted_table_name}.{$quoted_name}=[EXCLUDED].{$quoted_name}";
            }
            $on_condition[] = $constraint_condition;
        }
        $on = $this->build_condition($on_condition, $params);
        list(, $placeholders, $values, $params) = $this->prepare_insert_values($table, $insert_columns, $params);
        /**
         * Fix number of select query params for old MSSQL version that does not support offset correctly.
         * @see QueryBuilder::oldBuildOrderByAndLimit
         */
        $insert_names_using = $insert_names;
        if (strstr($values, 'rowNum = ROW_NUMBER()') !== false) {
            $insert_names_using = array_merge(['[rowNum]'], $insert_names);
        }
        $merge_sql = 'MERGE ' . $this->db->quote_table_name($table) . ' WITH (HOLDLOCK) ' . 'USING (' . (!empty($placeholders) ? 'VALUES (' . implode(', ', $placeholders) . ')' : ltrim($values, ' ')) . ') AS [EXCLUDED] (' . implode(', ', $insert_names_using) . ') ' . "ON ({$on})";
        $insert_values = [];
        foreach ($insert_names as $name) {
            $quoted_name = $this->db->quote_column_name($name);
            if (strrpos($quoted_name, '.') === false) {
                $quoted_name = '[EXCLUDED].' . $quoted_name;
            }
            $insert_values[] = $quoted_name;
        }
        $insert_sql = 'INSERT (' . implode(', ', $insert_names) . ')' . ' VALUES (' . implode(', ', $insert_values) . ')';
        if ($update_columns === false) {
            return "{$merge_sql} WHEN NOT MATCHED THEN {$insert_sql};";
        }
        if ($update_columns === true) {
            $update_columns = [];
            foreach ($update_names as $name) {
                $quoted_name = $this->db->quote_column_name($name);
                if (strrpos($quoted_name, '.') === false) {
                    $quoted_name = '[EXCLUDED].' . $quoted_name;
                }
                $update_columns[$name] = new Expression($quoted_name);
            }
        }
        $update_columns = $this->normalize_table_row_data($table, $update_columns, $params);
        list($updates, $params) = $this->prepare_update_sets($table, $update_columns, $params);
        $update_sql = 'UPDATE SET ' . implode(', ', $updates);
        return "{$merge_sql} WHEN MATCHED THEN {$update_sql} WHEN NOT MATCHED THEN {$insert_sql};";
    }
    /**
     * {@inheritdoc}
     */
    public function update($table, $columns, $condition, &$params)
    {
        return parent::update($table, $this->normalize_table_row_data($table, $columns, $params), $condition, $params);
    }
    /**
     * {@inheritdoc}
     */
    public function get_column_type($type)
    {
        $column_type = parent::get_column_type($type);
        // remove unsupported keywords
        $column_type = preg_replace("/\\s*comment '.*'/i", '', $column_type);
        $column_type = preg_replace('/ first$/i', '', $column_type);
        return $column_type;
    }
    /**
     * {@inheritdoc}
     */
    protected function extract_alias($table)
    {
        if (preg_match('/^\[.*\]$/', $table)) {
            return false;
        }
        return parent::extract_alias($table);
    }
    /**
     * Builds a SQL statement for dropping constraints for column of table.
     *
     * @param string $table the table whose constraint is to be dropped. The name will be properly quoted by the method.
     * @param string $column the column whose constraint is to be dropped. The name will be properly quoted by the method.
     * @param string $type type of constraint, leave empty for all type of constraints(for example: D - default, 'UQ' - unique, 'C' - check)
     * @see https://docs.microsoft.com/sql/relational-databases/system-catalog-views/sys-objects-transact-sql
     * @return string the DROP CONSTRAINTS SQL
     */
    private function drop_constraints_for_column($table, $column, $type = '')
    {
        return "DECLARE @tableName VARCHAR(MAX) = '" . $this->db->quote_table_name($table) . "'\nDECLARE @columnName VARCHAR(MAX) = '{$column}'\n\nWHILE 1=1 BEGIN\n    DECLARE @constraintName NVARCHAR(128)\n    SET @constraintName = (SELECT TOP 1 OBJECT_NAME(cons.[object_id])\n        FROM (\n            SELECT sc.[constid] object_id\n            FROM [sys].[sysconstraints] sc\n            JOIN [sys].[columns] c ON c.[object_id]=sc.[id] AND c.[column_id]=sc.[colid] AND c.[name]=@columnName\n            WHERE sc.[id] = OBJECT_ID(@tableName)\n            UNION\n            SELECT object_id(i.[name]) FROM [sys].[indexes] i\n            JOIN [sys].[columns] c ON c.[object_id]=i.[object_id] AND c.[name]=@columnName\n            JOIN [sys].[index_columns] ic ON ic.[object_id]=i.[object_id] AND i.[index_id]=ic.[index_id] AND c.[column_id]=ic.[column_id]\n            WHERE i.[is_unique_constraint]=1 and i.[object_id]=OBJECT_ID(@tableName)\n        ) cons\n        JOIN [sys].[objects] so ON so.[object_id]=cons.[object_id]\n        " . (!empty($type) ? " WHERE so.[type]='{$type}'" : '') . ")\n    IF @constraintName IS NULL BREAK\n    EXEC (N'ALTER TABLE ' + @tableName + ' DROP CONSTRAINT [' + @constraintName + ']')\nEND";
    }
    /**
     * Drop all constraints before column delete
     * {@inheritdoc}
     */
    public function drop_column($table, $column)
    {
        return $this->drop_constraints_for_column($table, $column) . "\nALTER TABLE " . $this->db->quote_table_name($table) . ' DROP COLUMN ' . $this->db->quote_column_name($column);
    }
}