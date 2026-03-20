<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mssql;

use Yii;
use yii\db\Check_Constraint;
use yii\db\Constraint;
use yii\db\Constraint_Finder_Interface;
use yii\db\Constraint_Finder_Trait;
use yii\db\Default_Value_Constraint;
use yii\db\Foreign_Key_Constraint;
use yii\db\Index_Constraint;
use yii\db\Schema as BaseSchema;
use yii\db\View_Finder_Trait;
use yii\helpers\Array_Helper;
/**
 * Schema is the class for retrieving metadata from MS SQL Server databases (version 2008 and above).
 *
 * @author Timur Ruziev <resurtm@gmail.com>
 * @since 2.0
 *
 * @template T of ColumnSchema = ColumnSchema
 * @extends BaseSchema<T>
 */
class Schema extends Base_Schema implements Constraint_Finder_Interface
{
    use View_Finder_Trait;
    use Constraint_Finder_Trait;
    /**
     * {@inheritdoc}
     */
    public $column_schema_class = 'yii\db\mssql\ColumnSchema';
    /**
     * @var string the default schema used for the current session.
     */
    public $default_schema = 'dbo';
    /**
     * @var array mapping from physical column types (keys) to abstract column types (values)
     */
    public $type_map = [
        // exact numbers
        'bigint' => self::TYPE_BIGINT,
        'numeric' => self::TYPE_DECIMAL,
        'bit' => self::TYPE_SMALLINT,
        'smallint' => self::TYPE_SMALLINT,
        'decimal' => self::TYPE_DECIMAL,
        'smallmoney' => self::TYPE_MONEY,
        'int' => self::TYPE_INTEGER,
        'tinyint' => self::TYPE_TINYINT,
        'money' => self::TYPE_MONEY,
        // approximate numbers
        'float' => self::TYPE_FLOAT,
        'double' => self::TYPE_DOUBLE,
        'real' => self::TYPE_FLOAT,
        // date and time
        'date' => self::TYPE_DATE,
        'datetimeoffset' => self::TYPE_DATETIME,
        'datetime2' => self::TYPE_DATETIME,
        'smalldatetime' => self::TYPE_DATETIME,
        'datetime' => self::TYPE_DATETIME,
        'time' => self::TYPE_TIME,
        // character strings
        'char' => self::TYPE_CHAR,
        'varchar' => self::TYPE_STRING,
        'text' => self::TYPE_TEXT,
        // unicode character strings
        'nchar' => self::TYPE_CHAR,
        'nvarchar' => self::TYPE_STRING,
        'ntext' => self::TYPE_TEXT,
        // binary strings
        'binary' => self::TYPE_BINARY,
        'varbinary' => self::TYPE_BINARY,
        'image' => self::TYPE_BINARY,
        // other data types
        // 'cursor' type cannot be used with tables
        'timestamp' => self::TYPE_TIMESTAMP,
        'hierarchyid' => self::TYPE_STRING,
        'uniqueidentifier' => self::TYPE_STRING,
        'sql_variant' => self::TYPE_STRING,
        'xml' => self::TYPE_STRING,
        'table' => self::TYPE_STRING,
    ];
    /**
     * {@inheritdoc}
     */
    protected $table_quote_character = ['[', ']'];
    /**
     * {@inheritdoc}
     */
    protected $column_quote_character = ['[', ']'];
    /**
     * Resolves the table name and schema name (if any).
     * @param string $name the table name
     * @return TableSchema resolved table, schema, etc. names.
     */
    protected function resolve_table_name($name): \yii\db\mssql\Table_Schema
    {
        $resolved_name = new Table_Schema();
        $parts = $this->get_table_name_parts($name);
        $part_count = count($parts);
        if ($part_count === 4) {
            // server name, catalog name, schema name and table name passed
            $resolved_name->catalog_name = $parts[1];
            $resolved_name->schema_name = $parts[2];
            $resolved_name->name = $parts[3];
            $resolved_name->full_name = $resolved_name->catalog_name . '.' . $resolved_name->schema_name . '.' . $resolved_name->name;
        } elseif ($part_count === 3) {
            // catalog name, schema name and table name passed
            $resolved_name->catalog_name = $parts[0];
            $resolved_name->schema_name = $parts[1];
            $resolved_name->name = $parts[2];
            $resolved_name->full_name = $resolved_name->catalog_name . '.' . $resolved_name->schema_name . '.' . $resolved_name->name;
        } elseif ($part_count === 2) {
            // only schema name and table name passed
            $resolved_name->schema_name = $parts[0];
            $resolved_name->name = $parts[1];
            $resolved_name->full_name = ($resolved_name->schema_name !== $this->default_schema ? $resolved_name->schema_name . '.' : '') . $resolved_name->name;
        } else {
            // only table name passed
            $resolved_name->schema_name = $this->default_schema;
            $resolved_name->full_name = $resolved_name->name = $parts[0];
        }
        return $resolved_name;
    }
    /**
     * {@inheritDoc}
     * @param string $name
     * @since 2.0.22
     */
    protected function get_table_name_parts($name): array
    {
        $parts = [$name];
        preg_match_all('/([^.\[\]]+)|\[([^\[\]]+)\]/', $name, $matches);
        if (isset($matches[0]) && is_array($matches[0]) && !empty($matches[0])) {
            $parts = $matches[0];
        }
        return str_replace(['[', ']'], '', $parts);
    }
    /**
     * {@inheritdoc}
     * @see https://docs.microsoft.com/en-us/sql/relational-databases/system-catalog-views/sys-database-principals-transact-sql
     */
    protected function find_schema_names()
    {
        static $sql = <<<'SQL'
        SELECT [s].[name]
        FROM [sys].[schemas] AS [s]
        INNER JOIN [sys].[database_principals] AS [p] ON [p].[principal_id] = [s].[principal_id]
        WHERE [p].[is_fixed_role] = 0 AND [p].[sid] IS NOT NULL
        ORDER BY [s].[name] ASC
        SQL;
        return $this->db->create_command($sql)->query_column();
    }
    /**
     * {@inheritdoc}
     */
    protected function find_table_names($schema = '')
    {
        if ($schema === '') {
            $schema = $this->default_schema;
        }
        $sql = <<<'SQL'
        SELECT [t].[table_name]
        FROM [INFORMATION_SCHEMA].[TABLES] AS [t]
        WHERE [t].[table_schema] = :schema AND [t].[table_type] IN ('BASE TABLE', 'VIEW')
        ORDER BY [t].[table_name]
        SQL;
        return $this->db->create_command($sql, [':schema' => $schema])->query_column();
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_schema($name): ?\yii\db\mssql\Table_Schema
    {
        $table = new Table_Schema();
        $this->resolve_table_names($table, $name);
        $this->find_primary_keys($table);
        if ($this->find_columns($table)) {
            $this->find_foreign_keys($table);
            return $table;
        }
        return null;
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    protected function get_schema_metadata($schema, $type, $refresh): array
    {
        $metadata = [];
        $method_name = 'getTable' . ucfirst($type);
        $table_names = array_map(fn(string $table) => $this->quote_simple_table_name($table), $this->get_table_names($schema, $refresh));
        foreach ($table_names as $name) {
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
            [i].[name] AS [name],
            [iccol].[name] AS [column_name],
            [i].[is_unique] AS [index_is_unique],
            [i].[is_primary_key] AS [index_is_primary]
        FROM [sys].[indexes] AS [i]
        INNER JOIN [sys].[index_columns] AS [ic]
            ON [ic].[object_id] = [i].[object_id] AND [ic].[index_id] = [i].[index_id]
        INNER JOIN [sys].[columns] AS [iccol]
            ON [iccol].[object_id] = [ic].[object_id] AND [iccol].[column_id] = [ic].[column_id]
        WHERE [i].[object_id] = OBJECT_ID(:fullName)
        ORDER BY [ic].[key_ordinal] ASC
        SQL;
        $resolved_name = $this->resolve_table_name($table_name);
        $indexes = $this->db->create_command($sql, [':fullName' => $resolved_name->full_name])->query_all();
        $indexes = $this->normalize_pdo_row_key_case($indexes, true);
        $indexes = Array_Helper::index($indexes, null, 'name');
        $result = [];
        foreach ($indexes as $name => $index) {
            $result[] = new Index_Constraint(['isPrimary' => (bool) $index[0]['index_is_primary'], 'isUnique' => (bool) $index[0]['index_is_unique'], 'name' => $name, 'columnNames' => Array_Helper::get_column($index, 'column_name')]);
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
     */
    protected function load_table_checks($table_name)
    {
        return $this->load_table_constraints($table_name, 'checks');
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_default_values($table_name)
    {
        return $this->load_table_constraints($table_name, 'defaults');
    }
    /**
     * {@inheritdoc}
     */
    public function create_savepoint($name): void
    {
        $this->db->create_command("SAVE TRANSACTION {$name}")->execute();
    }
    /**
     * {@inheritdoc}
     */
    public function release_savepoint($name): void
    {
        // does nothing as MSSQL does not support this
    }
    /**
     * {@inheritdoc}
     */
    public function roll_back_savepoint($name): void
    {
        $this->db->create_command("ROLLBACK TRANSACTION {$name}")->execute();
    }
    /**
     * Creates a query builder for the MSSQL database.
     * @return QueryBuilder query builder interface.
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
        $parts = $this->get_table_name_parts($name);
        $part_count = count($parts);
        if ($part_count === 4) {
            // server name, catalog name, schema name and table name passed
            $table->catalog_name = $parts[1];
            $table->schema_name = $parts[2];
            $table->name = $parts[3];
            $table->full_name = $table->catalog_name . '.' . $table->schema_name . '.' . $table->name;
        } elseif ($part_count === 3) {
            // catalog name, schema name and table name passed
            $table->catalog_name = $parts[0];
            $table->schema_name = $parts[1];
            $table->name = $parts[2];
            $table->full_name = $table->catalog_name . '.' . $table->schema_name . '.' . $table->name;
        } elseif ($part_count === 2) {
            // only schema name and table name passed
            $table->schema_name = $parts[0];
            $table->name = $parts[1];
            $table->full_name = $table->schema_name !== $this->default_schema ? $table->schema_name . '.' . $table->name : $table->name;
        } else {
            // only table name passed
            $table->schema_name = $this->default_schema;
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
        $is_version2017or_later = version_compare($this->db->get_schema()->get_server_version(), '14', '>=');
        $column = $this->create_column_schema();
        $column->name = $info['column_name'];
        $column->allow_null = $info['is_nullable'] === 'YES';
        $column->db_type = $info['data_type'];
        $column->enum_values = [];
        // mssql has only vague equivalents to enum
        $column->is_primary_key = null;
        // primary key will be determined in findColumns() method
        $column->auto_increment = $info['is_identity'] == 1;
        $column->is_computed = (bool) $info['is_computed'];
        $column->unsigned = stripos($column->db_type, 'unsigned') !== false;
        $column->comment = $info['comment'] ?? '';
        $column->type = self::TYPE_STRING;
        if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $column->db_type, $matches)) {
            $type = $matches[1];
            if (isset($this->type_map[$type])) {
                $column->type = $this->type_map[$type];
            }
            if ($is_version2017or_later && $type === 'bit') {
                $column->type = 'boolean';
            }
            if (!empty($matches[2])) {
                $values = explode(',', $matches[2]);
                $column->size = $column->precision = (int) $values[0];
                if (isset($values[1])) {
                    $column->scale = (int) $values[1];
                }
                if ($is_version2017or_later === false) {
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
        }
        $column->php_type = $this->get_column_php_type($column);
        if ($info['column_default'] === '(NULL)') {
            $info['column_default'] = null;
        }
        if (!$column->is_primary_key && ($column->type !== 'timestamp' || $info['column_default'] !== 'CURRENT_TIMESTAMP')) {
            $column->default_value = $column->default_php_typecast($info['column_default']);
        }
        return $column;
    }
    /**
     * Collects the metadata of table columns.
     * @param TableSchema $table the table metadata
     * @return bool whether the table exists in the database
     */
    protected function find_columns($table): bool
    {
        $columns_table_name = 'INFORMATION_SCHEMA.COLUMNS';
        $where_sql = '[t1].[table_name] = ' . $this->db->quote_value($table->name);
        if ($table->catalog_name !== null) {
            $columns_table_name = "{$table->catalog_name}.{$columns_table_name}";
            $where_sql .= " AND [t1].[table_catalog] = '{$table->catalog_name}'";
        }
        if ($table->schema_name !== null) {
            $where_sql .= " AND [t1].[table_schema] = '{$table->schema_name}'";
        }
        $columns_table_name = $this->quote_table_name($columns_table_name);
        $sql = <<<SQL
        SELECT
         [t1].[column_name],
         [t1].[is_nullable],
         CASE WHEN [t1].[data_type] IN ('char','varchar','nchar','nvarchar','binary','varbinary') THEN
            CASE WHEN [t1].[character_maximum_length] = NULL OR [t1].[character_maximum_length] = -1 THEN
                [t1].[data_type]
            ELSE
                [t1].[data_type] + '(' + LTRIM(RTRIM(CONVERT(CHAR,[t1].[character_maximum_length]))) + ')'
            END
         ELSE
            [t1].[data_type]
         END AS 'data_type',
         [t1].[column_default],
         COLUMNPROPERTY(OBJECT_ID([t1].[table_schema] + '.' + [t1].[table_name]), [t1].[column_name], 'IsIdentity') AS is_identity,
         COLUMNPROPERTY(OBJECT_ID([t1].[table_schema] + '.' + [t1].[table_name]), [t1].[column_name], 'IsComputed') AS is_computed,
         (
            SELECT CONVERT(VARCHAR, [t2].[value])
        \t\tFROM [sys].[extended_properties] AS [t2]
        \t\tWHERE
        \t\t\t[t2].[class] = 1 AND
        \t\t\t[t2].[class_desc] = 'OBJECT_OR_COLUMN' AND
        \t\t\t[t2].[name] = 'MS_Description' AND
        \t\t\t[t2].[major_id] = OBJECT_ID([t1].[TABLE_SCHEMA] + '.' + [t1].[table_name]) AND
        \t\t\t[t2].[minor_id] = COLUMNPROPERTY(OBJECT_ID([t1].[TABLE_SCHEMA] + '.' + [t1].[TABLE_NAME]), [t1].[COLUMN_NAME], 'ColumnID')
         ) as comment
        FROM {$columns_table_name} AS [t1]
        WHERE {$where_sql}
        SQL;
        try {
            $columns = $this->db->create_command($sql)->query_all();
            if (empty($columns)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        foreach ($columns as $column) {
            $column = $this->load_column_schema($column);
            foreach ($table->primary_key as $primary_key) {
                if (strcasecmp($column->name, $primary_key) === 0) {
                    $column->is_primary_key = true;
                    break;
                }
            }
            if ($column->is_primary_key && $column->auto_increment) {
                $table->sequence_name = '';
            }
            $table->columns[$column->name] = $column;
        }
        return true;
    }
    /**
     * Collects the constraint details for the given table and constraint type.
     * @param TableSchema $table
     * @param string $type either PRIMARY KEY or UNIQUE
     * @return array each entry contains index_name and field_name
     * @since 2.0.4
     */
    protected function find_table_constraints($table, $type)
    {
        $key_column_usage_table_name = 'INFORMATION_SCHEMA.KEY_COLUMN_USAGE';
        $table_constraints_table_name = 'INFORMATION_SCHEMA.TABLE_CONSTRAINTS';
        if ($table->catalog_name !== null) {
            $key_column_usage_table_name = $table->catalog_name . '.' . $key_column_usage_table_name;
            $table_constraints_table_name = $table->catalog_name . '.' . $table_constraints_table_name;
        }
        $key_column_usage_table_name = $this->quote_table_name($key_column_usage_table_name);
        $table_constraints_table_name = $this->quote_table_name($table_constraints_table_name);
        $sql = <<<SQL
        SELECT
            [kcu].[constraint_name] AS [index_name],
            [kcu].[column_name] AS [field_name]
        FROM {$key_column_usage_table_name} AS [kcu]
        LEFT JOIN {$table_constraints_table_name} AS [tc] ON
            [kcu].[table_schema] = [tc].[table_schema] AND
            [kcu].[table_name] = [tc].[table_name] AND
            [kcu].[constraint_name] = [tc].[constraint_name]
        WHERE
            [tc].[constraint_type] = :type AND
            [kcu].[table_name] = :tableName AND
            [kcu].[table_schema] = :schemaName
        SQL;
        return $this->db->create_command($sql, [':tableName' => $table->name, ':schemaName' => $table->schema_name, ':type' => $type])->query_all();
    }
    /**
     * Collects the primary key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function find_primary_keys($table)
    {
        $result = [];
        foreach ($this->find_table_constraints($table, 'PRIMARY KEY') as $row) {
            $result[] = $row['field_name'];
        }
        $table->primary_key = $result;
    }
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function find_foreign_keys($table)
    {
        $object = $table->name;
        if ($table->schema_name !== null) {
            $object = $table->schema_name . '.' . $object;
        }
        if ($table->catalog_name !== null) {
            $object = $table->catalog_name . '.' . $object;
        }
        // please refer to the following page for more details:
        // http://msdn2.microsoft.com/en-us/library/aa175805(SQL.80).aspx
        $sql = <<<'SQL'
        SELECT
        	[fk].[name] AS [fk_name],
        	[cp].[name] AS [fk_column_name],
        	OBJECT_NAME([fk].[referenced_object_id]) AS [uq_table_name],
        	[cr].[name] AS [uq_column_name]
        FROM
        	[sys].[foreign_keys] AS [fk]
        	INNER JOIN [sys].[foreign_key_columns] AS [fkc] ON
        		[fk].[object_id] = [fkc].[constraint_object_id]
        	INNER JOIN [sys].[columns] AS [cp] ON
        		[fk].[parent_object_id] = [cp].[object_id] AND
        		[fkc].[parent_column_id] = [cp].[column_id]
        	INNER JOIN [sys].[columns] AS [cr] ON
        		[fk].[referenced_object_id] = [cr].[object_id] AND
        		[fkc].[referenced_column_id] = [cr].[column_id]
        WHERE
        	[fk].[parent_object_id] = OBJECT_ID(:object)
        SQL;
        $rows = $this->db->create_command($sql, [':object' => $object])->query_all();
        $table->foreign_keys = [];
        foreach ($rows as $row) {
            if (!isset($table->foreign_keys[$row['fk_name']])) {
                $table->foreign_keys[$row['fk_name']][] = $row['uq_table_name'];
            }
            $table->foreign_keys[$row['fk_name']][$row['fk_column_name']] = $row['uq_column_name'];
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function find_view_names($schema = '')
    {
        if ($schema === '') {
            $schema = $this->default_schema;
        }
        $sql = <<<'SQL'
        SELECT [t].[table_name]
        FROM [INFORMATION_SCHEMA].[TABLES] AS [t]
        WHERE [t].[table_schema] = :schema AND [t].[table_type] = 'VIEW'
        ORDER BY [t].[table_name]
        SQL;
        return $this->db->create_command($sql, [':schema' => $schema])->query_column();
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
     * @since 2.0.4
     */
    public function find_unique_indexes($table): array
    {
        $result = [];
        foreach ($this->find_table_constraints($table, 'UNIQUE') as $row) {
            $result[$row['index_name']][] = $row['field_name'];
        }
        return $result;
    }
    /**
     * Loads multiple types of constraints and returns the specified ones.
     * @param string $tableName table name.
     * @param string $returnType return type:
     * - primaryKey
     * - foreignKeys
     * - uniques
     * - checks
     * - defaults
     * @return mixed constraints.
     */
    private function load_table_constraints($table_name, string $return_type)
    {
        static $sql = <<<'SQL'
        SELECT
            [o].[name] AS [name],
            COALESCE([ccol].[name], [dcol].[name], [fccol].[name], [kiccol].[name]) AS [column_name],
            RTRIM([o].[type]) AS [type],
            OBJECT_SCHEMA_NAME([f].[referenced_object_id]) AS [foreign_table_schema],
            OBJECT_NAME([f].[referenced_object_id]) AS [foreign_table_name],
            [ffccol].[name] AS [foreign_column_name],
            [f].[update_referential_action_desc] AS [on_update],
            [f].[delete_referential_action_desc] AS [on_delete],
            [c].[definition] AS [check_expr],
            [d].[definition] AS [default_expr]
        FROM (SELECT OBJECT_ID(:fullName) AS [object_id]) AS [t]
        INNER JOIN [sys].[objects] AS [o]
            ON [o].[parent_object_id] = [t].[object_id] AND [o].[type] IN ('PK', 'UQ', 'C', 'D', 'F')
        LEFT JOIN [sys].[check_constraints] AS [c]
            ON [c].[object_id] = [o].[object_id]
        LEFT JOIN [sys].[columns] AS [ccol]
            ON [ccol].[object_id] = [c].[parent_object_id] AND [ccol].[column_id] = [c].[parent_column_id]
        LEFT JOIN [sys].[default_constraints] AS [d]
            ON [d].[object_id] = [o].[object_id]
        LEFT JOIN [sys].[columns] AS [dcol]
            ON [dcol].[object_id] = [d].[parent_object_id] AND [dcol].[column_id] = [d].[parent_column_id]
        LEFT JOIN [sys].[key_constraints] AS [k]
            ON [k].[object_id] = [o].[object_id]
        LEFT JOIN [sys].[index_columns] AS [kic]
            ON [kic].[object_id] = [k].[parent_object_id] AND [kic].[index_id] = [k].[unique_index_id]
        LEFT JOIN [sys].[columns] AS [kiccol]
            ON [kiccol].[object_id] = [kic].[object_id] AND [kiccol].[column_id] = [kic].[column_id]
        LEFT JOIN [sys].[foreign_keys] AS [f]
            ON [f].[object_id] = [o].[object_id]
        LEFT JOIN [sys].[foreign_key_columns] AS [fc]
            ON [fc].[constraint_object_id] = [o].[object_id]
        LEFT JOIN [sys].[columns] AS [fccol]
            ON [fccol].[object_id] = [fc].[parent_object_id] AND [fccol].[column_id] = [fc].[parent_column_id]
        LEFT JOIN [sys].[columns] AS [ffccol]
            ON [ffccol].[object_id] = [fc].[referenced_object_id] AND [ffccol].[column_id] = [fc].[referenced_column_id]
        ORDER BY [kic].[key_ordinal] ASC, [fc].[constraint_column_id] ASC
        SQL;
        $resolved_name = $this->resolve_table_name($table_name);
        $constraints = $this->db->create_command($sql, [':fullName' => $resolved_name->full_name])->query_all();
        $constraints = $this->normalize_pdo_row_key_case($constraints, true);
        $constraints = Array_Helper::index($constraints, null, ['type', 'name']);
        $result = ['primaryKey' => null, 'foreignKeys' => [], 'uniques' => [], 'checks' => [], 'defaults' => []];
        foreach ($constraints as $type => $names) {
            foreach ($names as $name => $constraint) {
                switch ($type) {
                    case 'PK':
                        $result['primaryKey'] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                    case 'F':
                        $result['foreignKeys'][] = new Foreign_Key_Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name'), 'foreignSchemaName' => $constraint[0]['foreign_table_schema'], 'foreignTableName' => $constraint[0]['foreign_table_name'], 'foreignColumnNames' => Array_Helper::get_column($constraint, 'foreign_column_name'), 'onDelete' => str_replace('_', '', $constraint[0]['on_delete']), 'onUpdate' => str_replace('_', '', $constraint[0]['on_update'])]);
                        break;
                    case 'UQ':
                        $result['uniques'][] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                    case 'C':
                        $result['checks'][] = new Check_Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name'), 'expression' => $constraint[0]['check_expr']]);
                        break;
                    case 'D':
                        $result['defaults'][] = new Default_Value_Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name'), 'value' => $constraint[0]['default_expr']]);
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
     * {@inheritdoc}
     */
    public function quote_column_name($name)
    {
        if (preg_match('/^\[.*\]$/', $name)) {
            return $name;
        }
        return parent::quote_column_name($name);
    }
    /**
     * Retrieving inserted data from a primary key request of type uniqueidentifier (for SQL Server 2005 or later)
     * {@inheritdoc}
     */
    public function insert($table, $columns)
    {
        $command = $this->db->create_command()->insert($table, $columns);
        if (!$command->execute()) {
            return false;
        }
        $is_version2005or_later = version_compare($this->db->get_schema()->get_server_version(), '9', '>=');
        $inserted = $is_version2005or_later ? $command->pdo_statement->fetch() : [];
        $table_schema = $this->get_table_schema($table);
        $result = [];
        foreach ($table_schema->primary_key as $name) {
            // @see https://github.com/yiisoft/yii2/issues/13828 & https://github.com/yiisoft/yii2/issues/17474
            if (isset($inserted[$name])) {
                $result[$name] = $inserted[$name];
            } elseif ($table_schema->columns[$name]->auto_increment) {
                // for a version earlier than 2005
                $result[$name] = $this->get_last_insert_id($table_schema->sequence_name);
            } elseif (isset($columns[$name])) {
                $result[$name] = $columns[$name];
            } else {
                $result[$name] = $table_schema->columns[$name]->default_value;
            }
        }
        return $result;
    }
    /**
     * {@inheritdoc}
     */
    public function create_column_schema_builder($type, $length = null)
    {
        return Yii::create_object(Column_Schema_Builder::class_name(), [$type, $length, $this->db]);
    }
}