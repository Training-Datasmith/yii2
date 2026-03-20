<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\pgsql;

use Yii;
use yii\base\Not_Supported_Exception;
use yii\db\Check_Constraint;
use yii\db\Constraint;
use yii\db\Constraint_Finder_Interface;
use yii\db\Constraint_Finder_Trait;
use yii\db\Expression;
use yii\db\Foreign_Key_Constraint;
use yii\db\Index_Constraint;
use yii\db\Schema as BaseSchema;
use yii\db\Table_Schema;
use yii\db\View_Finder_Trait;
use yii\helpers\Array_Helper;
/**
 * Schema is the class for retrieving metadata from a PostgreSQL database
 * (version 9.x and above).
 *
 * @author Gevik Babakhani <gevikb@gmail.com>
 * @since 2.0
 *
 * @template T of ColumnSchema = ColumnSchema
 * @extends BaseSchema<T>
 */
class Schema extends Base_Schema implements Constraint_Finder_Interface
{
    use View_Finder_Trait;
    use Constraint_Finder_Trait;
    public const TYPE_JSONB = 'jsonb';
    /**
     * @var string the default schema used for the current session.
     */
    public $default_schema = 'public';
    /**
     * {@inheritdoc}
     */
    public $column_schema_class = 'yii\db\pgsql\ColumnSchema';
    /**
     * @var array mapping from physical column types (keys) to abstract
     * column types (values)
     * @see https://www.postgresql.org/docs/current/datatype.html#DATATYPE-TABLE
     */
    public $type_map = [
        'bit' => self::TYPE_INTEGER,
        'bit varying' => self::TYPE_INTEGER,
        'varbit' => self::TYPE_INTEGER,
        'bool' => self::TYPE_BOOLEAN,
        'boolean' => self::TYPE_BOOLEAN,
        'box' => self::TYPE_STRING,
        'circle' => self::TYPE_STRING,
        'point' => self::TYPE_STRING,
        'line' => self::TYPE_STRING,
        'lseg' => self::TYPE_STRING,
        'polygon' => self::TYPE_STRING,
        'path' => self::TYPE_STRING,
        'character' => self::TYPE_CHAR,
        'char' => self::TYPE_CHAR,
        'bpchar' => self::TYPE_CHAR,
        'character varying' => self::TYPE_STRING,
        'varchar' => self::TYPE_STRING,
        'text' => self::TYPE_TEXT,
        'bytea' => self::TYPE_BINARY,
        'cidr' => self::TYPE_STRING,
        'inet' => self::TYPE_STRING,
        'macaddr' => self::TYPE_STRING,
        'real' => self::TYPE_FLOAT,
        'float4' => self::TYPE_FLOAT,
        'double precision' => self::TYPE_DOUBLE,
        'float8' => self::TYPE_DOUBLE,
        'decimal' => self::TYPE_DECIMAL,
        'numeric' => self::TYPE_DECIMAL,
        'money' => self::TYPE_MONEY,
        'smallint' => self::TYPE_SMALLINT,
        'int2' => self::TYPE_SMALLINT,
        'int4' => self::TYPE_INTEGER,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'int8' => self::TYPE_BIGINT,
        'oid' => self::TYPE_BIGINT,
        // should not be used. it's pg internal!
        'smallserial' => self::TYPE_SMALLINT,
        'serial2' => self::TYPE_SMALLINT,
        'serial4' => self::TYPE_INTEGER,
        'serial' => self::TYPE_INTEGER,
        'bigserial' => self::TYPE_BIGINT,
        'serial8' => self::TYPE_BIGINT,
        'pg_lsn' => self::TYPE_BIGINT,
        'date' => self::TYPE_DATE,
        'interval' => self::TYPE_STRING,
        'time without time zone' => self::TYPE_TIME,
        'time' => self::TYPE_TIME,
        'time with time zone' => self::TYPE_TIME,
        'timetz' => self::TYPE_TIME,
        'timestamp without time zone' => self::TYPE_TIMESTAMP,
        'timestamp' => self::TYPE_TIMESTAMP,
        'timestamp with time zone' => self::TYPE_TIMESTAMP,
        'timestamptz' => self::TYPE_TIMESTAMP,
        'abstime' => self::TYPE_TIMESTAMP,
        'tsquery' => self::TYPE_STRING,
        'tsvector' => self::TYPE_STRING,
        'txid_snapshot' => self::TYPE_STRING,
        'unknown' => self::TYPE_STRING,
        'uuid' => self::TYPE_STRING,
        'json' => self::TYPE_JSON,
        'jsonb' => self::TYPE_JSON,
        'xml' => self::TYPE_STRING,
    ];
    /**
     * {@inheritdoc}
     */
    protected $table_quote_character = '"';
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
     */
    protected function find_schema_names()
    {
        static $sql = <<<'SQL'
        SELECT "ns"."nspname"
        FROM "pg_namespace" AS "ns"
        WHERE "ns"."nspname" != 'information_schema' AND "ns"."nspname" NOT LIKE 'pg_%'
        ORDER BY "ns"."nspname" ASC
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
        SELECT c.relname AS table_name
        FROM pg_class c
        INNER JOIN pg_namespace ns ON ns.oid = c.relnamespace
        WHERE ns.nspname = :schemaName AND c.relkind IN ('r','v','m','f', 'p')
        ORDER BY c.relname
        SQL;
        return $this->db->create_command($sql, [':schemaName' => $schema])->query_column();
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
            "ic"."relname" AS "name",
            "ia"."attname" AS "column_name",
            "i"."indisunique" AS "index_is_unique",
            "i"."indisprimary" AS "index_is_primary"
        FROM "pg_class" AS "tc"
        INNER JOIN "pg_namespace" AS "tcns"
            ON "tcns"."oid" = "tc"."relnamespace"
        INNER JOIN "pg_index" AS "i"
            ON "i"."indrelid" = "tc"."oid"
        INNER JOIN "pg_class" AS "ic"
            ON "ic"."oid" = "i"."indexrelid"
        INNER JOIN "pg_attribute" AS "ia"
            ON "ia"."attrelid" = "i"."indexrelid"
        WHERE "tcns"."nspname" = :schemaName AND "tc"."relname" = :tableName
        ORDER BY "ia"."attnum" ASC
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
        throw new Not_Supported_Exception('PostgreSQL does not support default value constraints.');
    }
    /**
     * Creates a query builder for the PostgreSQL database.
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
        $parts = explode('.', str_replace('"', '', $name));
        if (isset($parts[1])) {
            $table->schema_name = $parts[0];
            $table->name = $parts[1];
        } else {
            $table->schema_name = $this->default_schema;
            $table->name = $parts[0];
        }
        $table->full_name = $table->schema_name !== $this->default_schema ? $table->schema_name . '.' . $table->name : $table->name;
    }
    /**
     * {@inheritdoc]
     */
    protected function find_view_names($schema = '')
    {
        if ($schema === '') {
            $schema = $this->default_schema;
        }
        $sql = <<<'SQL'
        SELECT c.relname AS table_name
        FROM pg_class c
        INNER JOIN pg_namespace ns ON ns.oid = c.relnamespace
        WHERE ns.nspname = :schemaName AND (c.relkind = 'v' OR c.relkind = 'm')
        ORDER BY c.relname
        SQL;
        return $this->db->create_command($sql, [':schemaName' => $schema])->query_column();
    }
    /**
     * Collects the foreign key column details for the given table.
     * @param TableSchema $table the table metadata
     */
    protected function find_constraints($table)
    {
        $table_name = $this->quote_value($table->name);
        $table_schema = $this->quote_value($table->schema_name);
        //We need to extract the constraints de hard way since:
        //https://www.postgresql.org/message-id/26677.1086673982@sss.pgh.pa.us
        $sql = <<<SQL
        select
            ct.conname as constraint_name,
            a.attname as column_name,
            fc.relname as foreign_table_name,
            fns.nspname as foreign_table_schema,
            fa.attname as foreign_column_name
        from
            (SELECT ct.conname, ct.conrelid, ct.confrelid, ct.conkey, ct.contype, ct.confkey, generate_subscripts(ct.conkey, 1) AS s
               FROM pg_constraint ct
            ) AS ct
            inner join pg_class c on c.oid=ct.conrelid
            inner join pg_namespace ns on c.relnamespace=ns.oid
            inner join pg_attribute a on a.attrelid=ct.conrelid and a.attnum = ct.conkey[ct.s]
            left join pg_class fc on fc.oid=ct.confrelid
            left join pg_namespace fns on fc.relnamespace=fns.oid
            left join pg_attribute fa on fa.attrelid=ct.confrelid and fa.attnum = ct.confkey[ct.s]
        where
            ct.contype='f'
            and c.relname={$table_name}
            and ns.nspname={$table_schema}
        order by
            fns.nspname, fc.relname, a.attnum
        SQL;
        $constraints = [];
        foreach ($this->db->create_command($sql)->query_all() as $constraint) {
            if ($this->db->slave_pdo->get_attribute(\PDO::ATTR_CASE) === \PDO::CASE_UPPER) {
                $constraint = array_change_key_case($constraint, CASE_LOWER);
            }
            if ($constraint['foreign_table_schema'] !== $this->default_schema) {
                $foreign_table = $constraint['foreign_table_schema'] . '.' . $constraint['foreign_table_name'];
            } else {
                $foreign_table = $constraint['foreign_table_name'];
            }
            $name = $constraint['constraint_name'];
            if (!isset($constraints[$name])) {
                $constraints[$name] = ['tableName' => $foreign_table, 'columns' => []];
            }
            $constraints[$name]['columns'][$constraint['column_name']] = $constraint['foreign_column_name'];
        }
        foreach ($constraints as $name => $constraint) {
            $table->foreign_keys[$name] = array_merge([$constraint['tableName']], $constraint['columns']);
        }
    }
    /**
     * Gets information about given table unique indexes.
     * @param TableSchema $table the table metadata
     * @return array with index and column names
     */
    protected function get_unique_index_information($table)
    {
        $sql = <<<'SQL'
        SELECT
            i.relname as indexname,
            pg_get_indexdef(idx.indexrelid, k + 1, TRUE) AS columnname
        FROM (
          SELECT *, generate_subscripts(indkey, 1) AS k
          FROM pg_index
        ) idx
        INNER JOIN pg_class i ON i.oid = idx.indexrelid
        INNER JOIN pg_class c ON c.oid = idx.indrelid
        INNER JOIN pg_namespace ns ON c.relnamespace = ns.oid
        WHERE idx.indisprimary = FALSE AND idx.indisunique = TRUE
        AND c.relname = :tableName AND ns.nspname = :schemaName
        ORDER BY i.relname, k
        SQL;
        return $this->db->create_command($sql, [':schemaName' => $table->schema_name, ':tableName' => $table->name])->query_all();
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
        $unique_indexes = [];
        foreach ($this->get_unique_index_information($table) as $row) {
            if ($this->db->slave_pdo->get_attribute(\PDO::ATTR_CASE) === \PDO::CASE_UPPER) {
                $row = array_change_key_case($row, CASE_LOWER);
            }
            $column = $row['columnname'];
            if (strncmp($column, '"', 1) === 0) {
                // postgres will quote names that are not lowercase-only
                // https://github.com/yiisoft/yii2/issues/10613
                $column = substr($column, 1, -1);
            }
            $unique_indexes[$row['indexname']][] = $column;
        }
        return $unique_indexes;
    }
    /**
     * Collects the metadata of table columns.
     * @param TableSchema $table the table metadata
     * @return bool whether the table exists in the database
     */
    protected function find_columns($table): bool
    {
        $table_name = $this->db->quote_value($table->name);
        $schema_name = $this->db->quote_value($table->schema_name);
        $or_identity = '';
        if (version_compare($this->db->server_version, '12.0', '>=')) {
            $or_identity = 'OR attidentity != \'\'';
        }
        $sql = <<<SQL
        SELECT
            d.nspname AS table_schema,
            c.relname AS table_name,
            a.attname AS column_name,
            COALESCE(td.typname, tb.typname, t.typname) AS data_type,
            COALESCE(td.typtype, tb.typtype, t.typtype) AS type_type,
            (SELECT nspname FROM pg_namespace WHERE oid = COALESCE(td.typnamespace, tb.typnamespace, t.typnamespace)) AS type_scheme,
            a.attlen AS character_maximum_length,
            pg_catalog.col_description(c.oid, a.attnum) AS column_comment,
            a.atttypmod AS modifier,
            a.attnotnull = false AS is_nullable,
            CAST(pg_get_expr(ad.adbin, ad.adrelid) AS varchar) AS column_default,
            coalesce(pg_get_expr(ad.adbin, ad.adrelid) ~ 'nextval',false) {$or_identity} AS is_autoinc,
            pg_get_serial_sequence(quote_ident(d.nspname) || '.' || quote_ident(c.relname), a.attname) AS sequence_name,
            CASE WHEN COALESCE(td.typtype, tb.typtype, t.typtype) = 'e'::char
                THEN array_to_string((SELECT array_agg(enumlabel) FROM pg_enum WHERE enumtypid = COALESCE(td.oid, tb.oid, a.atttypid))::varchar[], ',')
                ELSE NULL
            END AS enum_values,
            CASE atttypid
                 WHEN 21 /*int2*/ THEN 16
                 WHEN 23 /*int4*/ THEN 32
                 WHEN 20 /*int8*/ THEN 64
                 WHEN 1700 /*numeric*/ THEN
                      CASE WHEN atttypmod = -1
                       THEN null
                       ELSE ((atttypmod - 4) >> 16) & 65535
                       END
                 WHEN 700 /*float4*/ THEN 24 /*FLT_MANT_DIG*/
                 WHEN 701 /*float8*/ THEN 53 /*DBL_MANT_DIG*/
                 ELSE null
              END   AS numeric_precision,
              CASE
                WHEN atttypid IN (21, 23, 20) THEN 0
                WHEN atttypid IN (1700) THEN
                CASE
                    WHEN atttypmod = -1 THEN null
                    ELSE (atttypmod - 4) & 65535
                END
                   ELSE null
              END AS numeric_scale,
            CAST(
                     information_schema._pg_char_max_length(information_schema._pg_truetypid(a, t), information_schema._pg_truetypmod(a, t))
                     AS numeric
            ) AS size,
            a.attnum = any (ct.conkey) as is_pkey,
            COALESCE(NULLIF(a.attndims, 0), NULLIF(t.typndims, 0), (t.typcategory='A')::int) AS dimension
        FROM
            pg_class c
            LEFT JOIN pg_attribute a ON a.attrelid = c.oid
            LEFT JOIN pg_attrdef ad ON a.attrelid = ad.adrelid AND a.attnum = ad.adnum
            LEFT JOIN pg_type t ON a.atttypid = t.oid
            LEFT JOIN pg_type tb ON (a.attndims > 0 OR t.typcategory='A') AND t.typelem > 0 AND t.typelem = tb.oid OR t.typbasetype > 0 AND t.typbasetype = tb.oid
            LEFT JOIN pg_type td ON t.typndims > 0 AND t.typbasetype > 0 AND tb.typelem = td.oid
            LEFT JOIN pg_namespace d ON d.oid = c.relnamespace
            LEFT JOIN pg_constraint ct ON ct.conrelid = c.oid AND ct.contype = 'p'
        WHERE
            a.attnum > 0 AND t.typname != '' AND NOT a.attisdropped
            AND c.relname = {$table_name}
            AND d.nspname = {$schema_name}
        ORDER BY
            a.attnum;
        SQL;
        $columns = $this->db->create_command($sql)->query_all();
        if (empty($columns)) {
            return false;
        }
        foreach ($columns as $column) {
            if ($this->db->slave_pdo->get_attribute(\PDO::ATTR_CASE) === \PDO::CASE_UPPER) {
                $column = array_change_key_case($column, CASE_LOWER);
            }
            $column = $this->load_column_schema($column);
            $table->columns[$column->name] = $column;
            if ($column->is_primary_key) {
                $table->primary_key[] = $column->name;
                if ($table->sequence_name === null) {
                    $table->sequence_name = $column->sequence_name;
                }
                $column->default_value = null;
            } elseif ($column->default_value) {
                if (in_array($column->type, [self::TYPE_TIMESTAMP, self::TYPE_DATE, self::TYPE_TIME], true) && (in_array(strtoupper($column->default_value), ['NOW()', 'CURRENT_TIMESTAMP', 'CURRENT_DATE', 'CURRENT_TIME'], true) || false !== strpos($column->default_value, '('))) {
                    $column->default_value = new Expression($column->default_value);
                } elseif ($column->type === 'boolean') {
                    $column->default_value = $column->default_value === 'true';
                } elseif (preg_match("/^B'(.*?)'::/", $column->default_value, $matches)) {
                    $column->default_value = bindec($matches[1]);
                } elseif (preg_match("/^'(\\d+)'::\"bit\"\$/", $column->default_value, $matches)) {
                    $column->default_value = bindec($matches[1]);
                } elseif (preg_match("/^'(.*?)'::/", $column->default_value, $matches)) {
                    $column->default_value = $column->php_typecast($matches[1]);
                } elseif (preg_match('/^(\()?(.*?)(?(1)\))(?:::.+)?$/', $column->default_value, $matches)) {
                    if ($matches[2] === 'NULL') {
                        $column->default_value = null;
                    } else {
                        $column->default_value = $column->php_typecast($matches[2]);
                    }
                } else {
                    $column->default_value = $column->php_typecast($column->default_value);
                }
            }
        }
        return true;
    }
    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return T the column schema object
     */
    protected function load_column_schema(array $info)
    {
        /** @var ColumnSchema $column */
        $column = $this->create_column_schema();
        $column->allow_null = $info['is_nullable'];
        $column->auto_increment = $info['is_autoinc'];
        $column->comment = $info['column_comment'];
        if ($info['type_scheme'] !== null && !in_array($info['type_scheme'], [$this->default_schema, 'pg_catalog'], true)) {
            $column->db_type = $info['type_scheme'] . '.' . $info['data_type'];
        } else {
            $column->db_type = $info['data_type'];
        }
        $column->default_value = $info['column_default'];
        $column->enum_values = $info['enum_values'] !== null ? explode(',', str_replace(["''"], ["'"], $info['enum_values'])) : null;
        $column->unsigned = false;
        // has no meaning in PG
        $column->is_primary_key = $info['is_pkey'];
        $column->name = $info['column_name'];
        $column->precision = $info['numeric_precision'];
        $column->scale = $info['numeric_scale'];
        $column->size = $info['size'] === null ? null : (int) $info['size'];
        $column->dimension = (int) $info['dimension'];
        // pg_get_serial_sequence() doesn't track DEFAULT value change. GENERATED BY IDENTITY columns always have null default value
        if (isset($column->default_value) && preg_match("/nextval\\('\"?\\w+\"?\\.?\"?\\w+\"?'(::regclass)?\\)/", $column->default_value) === 1) {
            $column->sequence_name = preg_replace(['/nextval/', '/::/', '/regclass/', '/\'\)/', '/\(\'/'], '', $column->default_value);
        } elseif (isset($info['sequence_name'])) {
            $column->sequence_name = $this->resolve_table_name($info['sequence_name'])->full_name;
        }
        if (isset($this->type_map[$column->db_type])) {
            $column->type = $this->type_map[$column->db_type];
        } else {
            $column->type = self::TYPE_STRING;
        }
        $column->php_type = $this->get_column_php_type($column);
        return $column;
    }
    /**
     * {@inheritdoc}
     */
    public function insert($table, $columns)
    {
        $params = [];
        $sql = $this->db->get_query_builder()->insert($table, $columns, $params);
        $return_columns = $this->get_table_schema($table)->primary_key;
        if (!empty($return_columns)) {
            $returning = [];
            foreach ((array) $return_columns as $name) {
                $returning[] = $this->quote_column_name($name);
            }
            $sql .= ' RETURNING ' . implode(', ', $returning);
        }
        $command = $this->db->create_command($sql, $params);
        $command->prepare(false);
        $result = $command->query_one();
        return !$command->pdo_statement->row_count() ? false : $result;
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
            "c"."conname" AS "name",
            "a"."attname" AS "column_name",
            "c"."contype" AS "type",
            "ftcns"."nspname" AS "foreign_table_schema",
            "ftc"."relname" AS "foreign_table_name",
            "fa"."attname" AS "foreign_column_name",
            "c"."confupdtype" AS "on_update",
            "c"."confdeltype" AS "on_delete",
            pg_get_constraintdef("c"."oid") AS "check_expr"
        FROM "pg_class" AS "tc"
        INNER JOIN "pg_namespace" AS "tcns"
            ON "tcns"."oid" = "tc"."relnamespace"
        INNER JOIN "pg_constraint" AS "c"
            ON "c"."conrelid" = "tc"."oid"
        INNER JOIN "pg_attribute" AS "a"
            ON "a"."attrelid" = "c"."conrelid" AND "a"."attnum" = ANY ("c"."conkey")
        LEFT JOIN "pg_class" AS "ftc"
            ON "ftc"."oid" = "c"."confrelid"
        LEFT JOIN "pg_namespace" AS "ftcns"
            ON "ftcns"."oid" = "ftc"."relnamespace"
        LEFT JOIN "pg_attribute" "fa"
            ON "fa"."attrelid" = "c"."confrelid" AND "fa"."attnum" = ANY ("c"."confkey")
        WHERE "tcns"."nspname" = :schemaName AND "tc"."relname" = :tableName
        ORDER BY "a"."attnum" ASC, "fa"."attnum" ASC
        SQL;
        static $action_types = ['a' => 'NO ACTION', 'r' => 'RESTRICT', 'c' => 'CASCADE', 'n' => 'SET NULL', 'd' => 'SET DEFAULT'];
        $resolved_name = $this->resolve_table_name($table_name);
        $constraints = $this->db->create_command($sql, [':schemaName' => $resolved_name->schema_name, ':tableName' => $resolved_name->name])->query_all();
        $constraints = $this->normalize_pdo_row_key_case($constraints, true);
        $constraints = Array_Helper::index($constraints, null, ['type', 'name']);
        $result = ['primaryKey' => null, 'foreignKeys' => [], 'uniques' => [], 'checks' => []];
        foreach ($constraints as $type => $names) {
            foreach ($names as $name => $constraint) {
                switch ($type) {
                    case 'p':
                        $result['primaryKey'] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                    case 'f':
                        $result['foreignKeys'][] = new Foreign_Key_Constraint(['name' => $name, 'columnNames' => array_keys(array_count_values(Array_Helper::get_column($constraint, 'column_name'))), 'foreignSchemaName' => $constraint[0]['foreign_table_schema'], 'foreignTableName' => $constraint[0]['foreign_table_name'], 'foreignColumnNames' => array_keys(array_count_values(Array_Helper::get_column($constraint, 'foreign_column_name'))), 'onDelete' => $action_types[$constraint[0]['on_delete']] ?? null, 'onUpdate' => $action_types[$constraint[0]['on_update']] ?? null]);
                        break;
                    case 'u':
                        $result['uniques'][] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'column_name')]);
                        break;
                    case 'c':
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