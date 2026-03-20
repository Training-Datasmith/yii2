<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\oci;

use Yii;
use yii\base\Invalid_Call_Exception;
use yii\base\Not_Supported_Exception;
use yii\db\Check_Constraint;
use yii\db\Column_Schema;
use yii\db\Connection;
use yii\db\Constraint;
use yii\db\Constraint_Finder_Interface;
use yii\db\Constraint_Finder_Trait;
use yii\db\Expression;
use yii\db\Foreign_Key_Constraint;
use yii\db\Index_Constraint;
use yii\db\Schema as BaseSchema;
use yii\db\Table_Schema;
use yii\helpers\Array_Helper;
/**
 * Schema is the class for retrieving metadata from an Oracle database.
 *
 * @property-read string $lastInsertID The row ID of the last row inserted, or the last value retrieved from
 * the sequence object.
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
     * @var array map of DB errors and corresponding exceptions
     * If left part is found in DB error message exception class from the right part is used.
     */
    public $exception_map = ['ORA-00001: unique constraint' => 'yii\db\IntegrityException'];
    /**
     * {@inheritdoc}
     */
    protected $table_quote_character = '"';
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->default_schema === null) {
            $username = $this->db->username;
            if (empty($username)) {
                $username = $this->db->masters[0]['username'] ?? '';
            }
            $this->default_schema = strtoupper($username);
        }
    }
    /**
     * {@inheritdoc}
     */
    protected function resolve_table_name($name): \yii\db\Table_Schema
    {
        $resolved_name = new Table_Schema();
        $parts = explode('.', str_replace('"', '', $name));
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
     * @see https://docs.oracle.com/cd/B28359_01/server.111/b28337/tdpsg_user_accounts.htm
     */
    protected function find_schema_names()
    {
        static $sql = <<<'SQL'
        SELECT "u"."USERNAME"
        FROM "DBA_USERS" "u"
        WHERE "u"."DEFAULT_TABLESPACE" NOT IN ('SYSTEM', 'SYSAUX')
        ORDER BY "u"."USERNAME" ASC
        SQL;
        return $this->db->create_command($sql)->query_column();
    }
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    protected function find_table_names($schema = ''): array
    {
        if ($schema === '') {
            $sql = <<<'SQL'
            SELECT
                TABLE_NAME
            FROM USER_TABLES
            UNION ALL
            SELECT
                VIEW_NAME AS TABLE_NAME
            FROM USER_VIEWS
            UNION ALL
            SELECT
                MVIEW_NAME AS TABLE_NAME
            FROM USER_MVIEWS
            ORDER BY TABLE_NAME
            SQL;
            $command = $this->db->create_command($sql);
        } else {
            $sql = <<<'SQL'
            SELECT
                OBJECT_NAME AS TABLE_NAME
            FROM ALL_OBJECTS
            WHERE
                OBJECT_TYPE IN ('TABLE', 'VIEW', 'MATERIALIZED VIEW')
                AND OWNER = :schema
            ORDER BY OBJECT_NAME
            SQL;
            $command = $this->db->create_command($sql, [':schema' => $schema]);
        }
        $rows = $command->query_all();
        $names = [];
        foreach ($rows as $row) {
            if ($this->db->slave_pdo->get_attribute(\PDO::ATTR_CASE) === \PDO::CASE_LOWER) {
                $row = array_change_key_case($row, CASE_UPPER);
            }
            $names[] = $row['TABLE_NAME'];
        }
        return $names;
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
            /*+ PUSH_PRED("ui") PUSH_PRED("uicol") PUSH_PRED("uc") */
            "ui"."INDEX_NAME" AS "name",
            "uicol"."COLUMN_NAME" AS "column_name",
            CASE "ui"."UNIQUENESS" WHEN 'UNIQUE' THEN 1 ELSE 0 END AS "index_is_unique",
            CASE WHEN "uc"."CONSTRAINT_NAME" IS NOT NULL THEN 1 ELSE 0 END AS "index_is_primary"
        FROM "SYS"."USER_INDEXES" "ui"
        LEFT JOIN "SYS"."USER_IND_COLUMNS" "uicol"
            ON "uicol"."INDEX_NAME" = "ui"."INDEX_NAME"
        LEFT JOIN "SYS"."USER_CONSTRAINTS" "uc"
            ON "uc"."OWNER" = "ui"."TABLE_OWNER" AND "uc"."CONSTRAINT_NAME" = "ui"."INDEX_NAME" AND "uc"."CONSTRAINT_TYPE" = 'P'
        WHERE "ui"."TABLE_OWNER" = :schemaName AND "ui"."TABLE_NAME" = :tableName
        ORDER BY "uicol"."COLUMN_POSITION" ASC
        SQL;
        $resolved_name = $this->resolve_table_name($table_name);
        $indexes = $this->db->create_command($sql, [':schemaName' => $resolved_name->schema_name, ':tableName' => $resolved_name->name])->query_all();
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
     * @throws NotSupportedException if this method is called.
     */
    protected function load_table_default_values($table_name)
    {
        throw new Not_Supported_Exception('Oracle does not support default value constraints.');
    }
    /**
     * {@inheritdoc}
     */
    public function release_savepoint($name): void
    {
        // does nothing as Oracle does not support this
    }
    /**
     * {@inheritdoc}
     */
    public function quote_simple_table_name($name)
    {
        return strpos($name, '"') !== false ? $name : '"' . $name . '"';
    }
    /**
     * {@inheritdoc}
     */
    public function create_query_builder()
    {
        return Yii::create_object(Query_Builder::class_name(), [$this->db]);
    }
    /**
     * {@inheritdoc}
     */
    public function create_column_schema_builder($type, $length = null)
    {
        return Yii::create_object(Column_Schema_Builder::class_name(), [$type, $length]);
    }
    /**
     * Resolves the table name and schema name (if any).
     *
     * @param TableSchema $table the table metadata object
     * @param string $name the table name
     */
    protected function resolve_table_names($table, $name)
    {
        $parts = explode('.', str_replace('"', '', $name));
        if (isset($parts[1])) {
            $table->schema_name = $parts[0];
            $table->name = $parts[1];
        } else {
            $table->schema_name = $this->default_schema;
            $table->name = $name;
        }
        $table->full_name = $table->schema_name !== $this->default_schema ? $table->schema_name . '.' . $table->name : $table->name;
    }
    /**
     * Collects the table column metadata.
     * @param TableSchema $table the table schema
     * @return bool whether the table exists
     */
    protected function find_columns($table): bool
    {
        $sql = <<<'SQL'
        SELECT
            A.COLUMN_NAME,
            A.DATA_TYPE,
            A.DATA_PRECISION,
            A.DATA_SCALE,
            (
              CASE A.CHAR_USED WHEN 'C' THEN A.CHAR_LENGTH
                ELSE A.DATA_LENGTH
              END
            ) AS DATA_LENGTH,
            A.NULLABLE,
            A.DATA_DEFAULT,
            COM.COMMENTS AS COLUMN_COMMENT
        FROM ALL_TAB_COLUMNS A
            INNER JOIN ALL_OBJECTS B ON B.OWNER = A.OWNER AND LTRIM(B.OBJECT_NAME) = LTRIM(A.TABLE_NAME)
            LEFT JOIN ALL_COL_COMMENTS COM ON (A.OWNER = COM.OWNER AND A.TABLE_NAME = COM.TABLE_NAME AND A.COLUMN_NAME = COM.COLUMN_NAME)
        WHERE
            A.OWNER = :schemaName
            AND B.OBJECT_TYPE IN ('TABLE', 'VIEW', 'MATERIALIZED VIEW')
            AND B.OBJECT_NAME = :tableName
        ORDER BY A.COLUMN_ID
        SQL;
        try {
            $columns = $this->db->create_command($sql, [':tableName' => $table->name, ':schemaName' => $table->schema_name])->query_all();
        } catch (\Exception $e) {
            return false;
        }
        if (empty($columns)) {
            return false;
        }
        foreach ($columns as $column) {
            if ($this->db->slave_pdo->get_attribute(\PDO::ATTR_CASE) === \PDO::CASE_LOWER) {
                $column = array_change_key_case($column, CASE_UPPER);
            }
            $c = $this->create_column($column);
            $table->columns[$c->name] = $c;
        }
        return true;
    }
    /**
     * Sequence name of table.
     *
     * @param string $tableName
     * @internal param \yii\db\TableSchema $table->name the table schema
     * @return string|null whether the sequence exists
     */
    protected function get_table_sequence_name($table_name)
    {
        $sequence_name_sql = <<<'SQL'
        SELECT
            UD.REFERENCED_NAME AS SEQUENCE_NAME
        FROM USER_DEPENDENCIES UD
            JOIN USER_TRIGGERS UT ON (UT.TRIGGER_NAME = UD.NAME)
        WHERE
            UT.TABLE_NAME = :tableName
            AND UD.TYPE = 'TRIGGER'
            AND UD.REFERENCED_TYPE = 'SEQUENCE'
        SQL;
        $sequence_name = $this->db->create_command($sequence_name_sql, [':tableName' => $table_name])->query_scalar();
        return $sequence_name === false ? null : $sequence_name;
    }
    /**
     * @Overrides method in class 'Schema'
     * @see https://www.php.net/manual/en/function.PDO-lastInsertId.php -> Oracle does not support this
     *
     * Returns the ID of the last inserted row or sequence value.
     * @param string $sequenceName name of the sequence object (required by some DBMS)
     * @return string the row ID of the last row inserted, or the last value retrieved from the sequence object
     * @throws InvalidCallException if the DB connection is not active
     */
    public function get_last_insert_id($sequence_name = '')
    {
        if ($this->db->is_active) {
            // get the last insert id from the master connection
            $sequence_name = $this->quote_simple_table_name($sequence_name);
            return $this->db->use_master(fn(Connection $db) => $db->create_command("SELECT {$sequence_name}.CURRVAL FROM DUAL")->query_scalar());
        }
        throw new Invalid_Call_Exception('DB Connection is not active.');
    }
    /**
     * Creates ColumnSchema instance.
     *
     * @return T
     */
    protected function create_column(array $column)
    {
        $c = $this->create_column_schema();
        $c->name = $column['COLUMN_NAME'];
        $c->allow_null = $column['NULLABLE'] === 'Y';
        $c->comment = $column['COLUMN_COMMENT'] ?? '';
        $c->is_primary_key = false;
        $this->extract_column_type($c, $column['DATA_TYPE'], $column['DATA_PRECISION'], $column['DATA_SCALE'], $column['DATA_LENGTH']);
        $this->extract_column_size($c, $column['DATA_TYPE'], $column['DATA_PRECISION'], $column['DATA_SCALE'], $column['DATA_LENGTH']);
        $c->php_type = $this->get_column_php_type($c);
        if (!$c->is_primary_key) {
            if (stripos((string) $column['DATA_DEFAULT'], 'timestamp') !== false) {
                $c->default_value = null;
            } else {
                $default_value = (string) $column['DATA_DEFAULT'];
                if ($c->type === 'timestamp' && $default_value === 'CURRENT_TIMESTAMP') {
                    $c->default_value = new Expression('CURRENT_TIMESTAMP');
                } else {
                    if (strlen($default_value) > 2 && strncmp($default_value, "'", 1) === 0 && substr($default_value, -1) === "'") {
                        $default_value = substr($default_value, 1, -1);
                    } else {
                        $default_value = trim($default_value);
                    }
                    $c->default_value = $c->php_typecast($default_value);
                }
            }
        }
        return $c;
    }
    /**
     * Finds constraints and fills them into TableSchema object passed.
     * @param TableSchema $table
     */
    protected function find_constraints($table)
    {
        $sql = <<<'SQL'
        SELECT
            /*+ PUSH_PRED(C) PUSH_PRED(D) PUSH_PRED(E) */
            D.CONSTRAINT_NAME,
            D.CONSTRAINT_TYPE,
            C.COLUMN_NAME,
            C.POSITION,
            D.R_CONSTRAINT_NAME,
            E.TABLE_NAME AS TABLE_REF,
            F.COLUMN_NAME AS COLUMN_REF,
            C.TABLE_NAME
        FROM ALL_CONS_COLUMNS C
            INNER JOIN ALL_CONSTRAINTS D ON D.OWNER = C.OWNER AND D.CONSTRAINT_NAME = C.CONSTRAINT_NAME
            LEFT JOIN ALL_CONSTRAINTS E ON E.OWNER = D.R_OWNER AND E.CONSTRAINT_NAME = D.R_CONSTRAINT_NAME
            LEFT JOIN ALL_CONS_COLUMNS F ON F.OWNER = E.OWNER AND F.CONSTRAINT_NAME = E.CONSTRAINT_NAME AND F.POSITION = C.POSITION
        WHERE
            C.OWNER = :schemaName
            AND C.TABLE_NAME = :tableName
        ORDER BY D.CONSTRAINT_NAME, C.POSITION
        SQL;
        $command = $this->db->create_command($sql, [':tableName' => $table->name, ':schemaName' => $table->schema_name]);
        $constraints = [];
        foreach ($command->query_all() as $row) {
            if ($this->db->slave_pdo->get_attribute(\PDO::ATTR_CASE) === \PDO::CASE_LOWER) {
                $row = array_change_key_case($row, CASE_UPPER);
            }
            if ($row['CONSTRAINT_TYPE'] === 'P') {
                $table->columns[$row['COLUMN_NAME']]->is_primary_key = true;
                $table->primary_key[] = $row['COLUMN_NAME'];
                if (empty($table->sequence_name)) {
                    $table->sequence_name = $this->get_table_sequence_name($table->name);
                }
            }
            if ($row['CONSTRAINT_TYPE'] !== 'R') {
                // this condition is not checked in SQL WHERE because of an Oracle Bug:
                // see https://github.com/yiisoft/yii2/pull/8844
                continue;
            }
            $name = $row['CONSTRAINT_NAME'];
            if (!isset($constraints[$name])) {
                $constraints[$name] = ['tableName' => $row['TABLE_REF'], 'columns' => []];
            }
            $constraints[$name]['columns'][$row['COLUMN_NAME']] = $row['COLUMN_REF'];
        }
        foreach ($constraints as $constraint) {
            $name = current(array_keys($constraint));
            $table->foreign_keys[$name] = array_merge([$constraint['tableName']], $constraint['columns']);
        }
    }
    /**
     * Returns all unique indexes for the given table.
     * Each array element is of the following structure:.
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
        $query = <<<'SQL'
        SELECT
            DIC.INDEX_NAME,
            DIC.COLUMN_NAME
        FROM ALL_INDEXES DI
            INNER JOIN ALL_IND_COLUMNS DIC ON DI.TABLE_NAME = DIC.TABLE_NAME AND DI.INDEX_NAME = DIC.INDEX_NAME
        WHERE
            DI.UNIQUENESS = 'UNIQUE'
            AND DIC.TABLE_OWNER = :schemaName
            AND DIC.TABLE_NAME = :tableName
        ORDER BY DIC.TABLE_NAME, DIC.INDEX_NAME, DIC.COLUMN_POSITION
        SQL;
        $result = [];
        $command = $this->db->create_command($query, [':tableName' => $table->name, ':schemaName' => $table->schema_name]);
        foreach ($command->query_all() as $row) {
            $result[$row['INDEX_NAME']][] = $row['COLUMN_NAME'];
        }
        return $result;
    }
    /**
     * Extracts the data types for the given column.
     * @param ColumnSchema $column
     * @param string $dbType DB type
     * @param string $precision total number of digits.
     * This parameter is available since version 2.0.4.
     * @param string $scale number of digits on the right of the decimal separator.
     * This parameter is available since version 2.0.4.
     * @param string $length length for character types.
     * This parameter is available since version 2.0.4.
     */
    protected function extract_column_type($column, $db_type, $precision, $scale, $length)
    {
        $column->db_type = $db_type;
        if (strpos($db_type, 'FLOAT') !== false || strpos($db_type, 'DOUBLE') !== false) {
            $column->type = 'double';
        } elseif (strpos($db_type, 'NUMBER') !== false) {
            if ($scale === null || $scale > 0) {
                $column->type = 'decimal';
            } else {
                $column->type = 'integer';
            }
        } elseif (strpos($db_type, 'INTEGER') !== false) {
            $column->type = 'integer';
        } elseif (strpos($db_type, 'BLOB') !== false) {
            $column->type = 'binary';
        } elseif (strpos($db_type, 'CLOB') !== false) {
            $column->type = 'text';
        } elseif (strpos($db_type, 'TIMESTAMP') !== false) {
            $column->type = 'timestamp';
        } else {
            $column->type = 'string';
        }
    }
    /**
     * Extracts size, precision and scale information from column's DB type.
     * @param ColumnSchema $column
     * @param string $dbType the column's DB type
     * @param string $precision total number of digits.
     * This parameter is available since version 2.0.4.
     * @param string $scale number of digits on the right of the decimal separator.
     * This parameter is available since version 2.0.4.
     * @param string $length length for character types.
     * This parameter is available since version 2.0.4.
     */
    protected function extract_column_size($column, $db_type, $precision, $scale, $length)
    {
        $column->size = trim((string) $length) === '' ? null : (int) $length;
        $column->precision = trim((string) $precision) === '' ? null : (int) $precision;
        $column->scale = trim((string) $scale) === '' ? null : (int) $scale;
    }
    /**
     * {@inheritdoc}
     */
    public function insert($table, $columns)
    {
        $params = [];
        $return_params = [];
        $sql = $this->db->get_query_builder()->insert($table, $columns, $params);
        $table_schema = $this->get_table_schema($table);
        $return_columns = $table_schema->primary_key;
        if (!empty($return_columns)) {
            $column_schemas = $table_schema->columns;
            $returning = [];
            foreach ((array) $return_columns as $name) {
                $ph_name = Query_Builder::PARAM_PREFIX . (count($params) + count($return_params));
                $return_params[$ph_name] = ['column' => $name, 'value' => ''];
                if (!isset($column_schemas[$name]) || $column_schemas[$name]->php_type !== 'integer') {
                    $return_params[$ph_name]['dataType'] = \PDO::PARAM_STR;
                } else {
                    $return_params[$ph_name]['dataType'] = \PDO::PARAM_INT;
                }
                $return_params[$ph_name]['size'] = $column_schemas[$name]->size ?? -1;
                $returning[] = $this->quote_column_name($name);
            }
            $sql .= ' RETURNING ' . implode(', ', $returning) . ' INTO ' . implode(', ', array_keys($return_params));
        }
        $command = $this->db->create_command($sql, $params);
        $command->prepare(false);
        foreach ($return_params as $name => &$value) {
            $command->pdo_statement->bind_param($name, $value['value'], $value['dataType'], $value['size']);
        }
        if (!$command->execute()) {
            return false;
        }
        $result = [];
        foreach ($return_params as $value) {
            $result[$value['column']] = $value['value'];
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
     * @return mixed constraints.
     */
    private function load_table_constraints($table_name, string $return_type)
    {
        static $sql = <<<'SQL'
        SELECT
            /*+ PUSH_PRED("uc") PUSH_PRED("uccol") PUSH_PRED("fuc") */
            "uc"."CONSTRAINT_NAME" AS "name",
            "uccol"."COLUMN_NAME" AS "column_name",
            "uc"."CONSTRAINT_TYPE" AS "type",
            "fuc"."OWNER" AS "foreign_table_schema",
            "fuc"."TABLE_NAME" AS "foreign_table_name",
            "fuccol"."COLUMN_NAME" AS "foreign_column_name",
            "uc"."DELETE_RULE" AS "on_delete",
            "uc"."SEARCH_CONDITION" AS "check_expr"
        FROM "USER_CONSTRAINTS" "uc"
        INNER JOIN "USER_CONS_COLUMNS" "uccol"
            ON "uccol"."OWNER" = "uc"."OWNER" AND "uccol"."CONSTRAINT_NAME" = "uc"."CONSTRAINT_NAME"
        LEFT JOIN "USER_CONSTRAINTS" "fuc"
            ON "fuc"."OWNER" = "uc"."R_OWNER" AND "fuc"."CONSTRAINT_NAME" = "uc"."R_CONSTRAINT_NAME"
        LEFT JOIN "USER_CONS_COLUMNS" "fuccol"
            ON "fuccol"."OWNER" = "fuc"."OWNER" AND "fuccol"."CONSTRAINT_NAME" = "fuc"."CONSTRAINT_NAME" AND "fuccol"."POSITION" = "uccol"."POSITION"
        WHERE "uc"."OWNER" = :schemaName AND "uc"."TABLE_NAME" = :tableName
        ORDER BY "uccol"."POSITION" ASC
        SQL;
        $resolved_name = $this->resolve_table_name($table_name);
        $constraints = $this->db->create_command($sql, [':schemaName' => $resolved_name->schema_name, ':tableName' => $resolved_name->name])->query_all();
        $constraints = $this->normalize_pdo_row_key_case($constraints, true);
        $constraints = Array_Helper::index($constraints, null, ['type', 'name']);
        $result = ['primaryKey' => null, 'foreignKeys' => [], 'uniques' => [], 'checks' => []];
        foreach ($constraints as $type => $names) {
            foreach ($names as $name => $constraint) {
                switch ($type) {
                    case 'P':
                        $result['primaryKey'] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                    case 'R':
                        $result['foreignKeys'][] = new Foreign_Key_Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name'), 'foreignSchemaName' => $constraint[0]['foreign_table_schema'], 'foreignTableName' => $constraint[0]['foreign_table_name'], 'foreignColumnNames' => Array_Helper::get_column($constraint, 'foreign_column_name'), 'onDelete' => $constraint[0]['on_delete'], 'onUpdate' => null]);
                        break;
                    case 'U':
                        $result['uniques'][] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                    case 'C':
                        $result['checks'][] = new Check_Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name'), 'expression' => $constraint[0]['check_expr']]);
                        break;
                }
            }
        }
        foreach ($result as $type => $data) {
            $this->set_table_metadata($table_name, $type, $data);
        }
        return $result[$return_type];
    }
}