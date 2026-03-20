<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\cubrid;

use Yii;
use yii\base\Not_Supported_Exception;
use yii\db\Column_Schema;
use yii\db\Constraint;
use yii\db\Constraint_Finder_Interface;
use yii\db\Constraint_Finder_Trait;
use yii\db\Expression;
use yii\db\Foreign_Key_Constraint;
use yii\db\Index_Constraint;
use yii\db\Schema as BaseSchema;
use yii\db\Table_Schema;
use yii\db\Transaction;
use yii\helpers\Array_Helper;
/**
 * Schema is the class for retrieving metadata from a CUBRID database (version 9.3.x and higher).
 *
 * @author Carsten Brandt <mail@cebe.cc>
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
     * Please refer to [CUBRID manual](https://www.cubrid.org/manual/en/9.3.0/sql/datatype.html) for
     * details on data types.
     */
    public $type_map = [
        // Numeric data types
        'short' => self::TYPE_SMALLINT,
        'smallint' => self::TYPE_SMALLINT,
        'int' => self::TYPE_INTEGER,
        'integer' => self::TYPE_INTEGER,
        'bigint' => self::TYPE_BIGINT,
        'numeric' => self::TYPE_DECIMAL,
        'decimal' => self::TYPE_DECIMAL,
        'float' => self::TYPE_FLOAT,
        'real' => self::TYPE_FLOAT,
        'double' => self::TYPE_DOUBLE,
        'double precision' => self::TYPE_DOUBLE,
        'monetary' => self::TYPE_MONEY,
        // Date/Time data types
        'date' => self::TYPE_DATE,
        'time' => self::TYPE_TIME,
        'timestamp' => self::TYPE_TIMESTAMP,
        'datetime' => self::TYPE_DATETIME,
        // String data types
        'char' => self::TYPE_CHAR,
        'varchar' => self::TYPE_STRING,
        'char varying' => self::TYPE_STRING,
        'nchar' => self::TYPE_CHAR,
        'nchar varying' => self::TYPE_STRING,
        'string' => self::TYPE_STRING,
        // BLOB/CLOB data types
        'blob' => self::TYPE_BINARY,
        'clob' => self::TYPE_BINARY,
        // Bit string data types
        'bit' => self::TYPE_INTEGER,
        'bit varying' => self::TYPE_INTEGER,
        // Collection data types (considered strings for now)
        'set' => self::TYPE_STRING,
        'multiset' => self::TYPE_STRING,
        'list' => self::TYPE_STRING,
        'sequence' => self::TYPE_STRING,
        'enum' => self::TYPE_STRING,
    ];
    /**
     * @var array map of DB errors and corresponding exceptions
     * If left part is found in DB error message exception class from the right part is used.
     */
    public $exception_map = ['Operation would have caused one or more unique constraint violations' => 'yii\db\IntegrityException'];
    /**
     * {@inheritdoc}
     */
    protected $table_quote_character = '"';
    /**
     * {@inheritdoc}
     * @return mixed[]
     */
    protected function find_table_names($schema = ''): array
    {
        $pdo = $this->db->get_slave_pdo(true);
        $tables = $pdo->cubrid_schema(\PDO::CUBRID_SCH_TABLE);
        $table_names = [];
        foreach ($tables as $table) {
            // do not list system tables
            if ($table['TYPE'] != 0) {
                $table_names[] = $table['NAME'];
            }
        }
        return $table_names;
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_schema($name): ?\yii\db\Table_Schema
    {
        $pdo = $this->db->get_slave_pdo(true);
        $table_info = $pdo->cubrid_schema(\PDO::CUBRID_SCH_TABLE, $name);
        if (!isset($table_info[0]['NAME'])) {
            return null;
        }
        $table = new Table_Schema();
        $table->full_name = $table->name = $table_info[0]['NAME'];
        $sql = 'SHOW FULL COLUMNS FROM ' . $this->quote_simple_table_name($table->name);
        $columns = $this->db->create_command($sql)->query_all();
        foreach ($columns as $info) {
            $column = $this->load_column_schema($info);
            $table->columns[$column->name] = $column;
        }
        $primary_keys = $pdo->cubrid_schema(\PDO::CUBRID_SCH_PRIMARY_KEY, $table->name);
        foreach ($primary_keys as $key) {
            $column = $table->columns[$key['ATTR_NAME']];
            $column->is_primary_key = true;
            $table->primary_key[] = $column->name;
            if ($column->auto_increment) {
                $table->sequence_name = '';
            }
        }
        $foreign_keys = $pdo->cubrid_schema(\PDO::CUBRID_SCH_IMPORTED_KEYS, $table->name);
        foreach ($foreign_keys as $key) {
            if (isset($table->foreign_keys[$key['FK_NAME']])) {
                $table->foreign_keys[$key['FK_NAME']][$key['FKCOLUMN_NAME']] = $key['PKCOLUMN_NAME'];
            } else {
                $table->foreign_keys[$key['FK_NAME']] = [$key['PKTABLE_NAME'], $key['FKCOLUMN_NAME'] => $key['PKCOLUMN_NAME']];
            }
        }
        return $table;
    }
    /**
     * {@inheritdoc}
     */
    protected function load_table_primary_key($table_name): ?\yii\db\Constraint
    {
        $primary_key = $this->db->get_slave_pdo(true)->cubrid_schema(\PDO::CUBRID_SCH_PRIMARY_KEY, $table_name);
        if (empty($primary_key)) {
            return null;
        }
        Array_Helper::multisort($primary_key, 'KEY_SEQ', SORT_ASC, SORT_NUMERIC);
        return new Constraint(['name' => $primary_key[0]['KEY_NAME'], 'columnNames' => Array_Helper::get_column($primary_key, 'ATTR_NAME')]);
    }
    /**
     * {@inheritdoc}
     * @return \yii\db\ForeignKeyConstraint[]
     */
    protected function load_table_foreign_keys($table_name): array
    {
        static $action_types = [0 => 'CASCADE', 1 => 'RESTRICT', 2 => 'NO ACTION', 3 => 'SET NULL'];
        $foreign_keys = $this->db->get_slave_pdo(true)->cubrid_schema(\PDO::CUBRID_SCH_IMPORTED_KEYS, $table_name);
        $foreign_keys = Array_Helper::index($foreign_keys, null, 'FK_NAME');
        Array_Helper::multisort($foreign_keys, 'KEY_SEQ', SORT_ASC, SORT_NUMERIC);
        $result = [];
        foreach ($foreign_keys as $name => $foreign_key) {
            $result[] = new Foreign_Key_Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($foreign_key, 'FKCOLUMN_NAME'), 'foreignTableName' => $foreign_key[0]['PKTABLE_NAME'], 'foreignColumnNames' => Array_Helper::get_column($foreign_key, 'PKCOLUMN_NAME'), 'onDelete' => $action_types[$foreign_key[0]['DELETE_RULE']] ?? null, 'onUpdate' => $action_types[$foreign_key[0]['UPDATE_RULE']] ?? null]);
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
     * @throws NotSupportedException if this method is called.
     */
    protected function load_table_checks($table_name)
    {
        throw new Not_Supported_Exception('CUBRID does not support check constraints.');
    }
    /**
     * {@inheritdoc}
     * @throws NotSupportedException if this method is called.
     */
    protected function load_table_default_values($table_name)
    {
        throw new Not_Supported_Exception('CUBRID does not support default value constraints.');
    }
    /**
     * {@inheritdoc}
     */
    public function release_savepoint($name): void
    {
        // does nothing as cubrid does not support this
    }
    /**
     * Creates a query builder for the CUBRID database.
     * @return QueryBuilder query builder instance
     */
    public function create_query_builder()
    {
        return Yii::create_object(Query_Builder::class_name(), [$this->db]);
    }
    /**
     * Loads the column information into a [[ColumnSchema]] object.
     * @param array $info column information
     * @return T the column schema object
     */
    protected function load_column_schema(array $info)
    {
        $column = $this->create_column_schema();
        $column->name = $info['Field'];
        $column->allow_null = $info['Null'] === 'YES';
        $column->is_primary_key = false;
        // primary key will be set by loadTableSchema() later
        $column->auto_increment = stripos($info['Extra'], 'auto_increment') !== false;
        $column->db_type = $info['Type'];
        $column->unsigned = strpos($column->db_type, 'unsigned') !== false;
        $column->type = self::TYPE_STRING;
        if (preg_match('/^([\w ]+)(?:\(([^\)]+)\))?$/', $column->db_type, $matches)) {
            $type = strtolower($matches[1]);
            $column->db_type = $type . (isset($matches[2]) ? "({$matches[2]})" : '');
            if (isset($this->type_map[$type])) {
                $column->type = $this->type_map[$type];
            }
            if (!empty($matches[2])) {
                if ($type === 'enum') {
                    $values = preg_split('/\s*,\s*/', $matches[2]);
                    foreach ($values as $i => $value) {
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
        if ($column->is_primary_key) {
            return $column;
        }
        if ($column->type === 'timestamp' && $info['Default'] === 'SYS_TIMESTAMP' || $column->type === 'datetime' && $info['Default'] === 'SYS_DATETIME' || $column->type === 'date' && $info['Default'] === 'SYS_DATE' || $column->type === 'time' && $info['Default'] === 'SYS_TIME') {
            $column->default_value = new Expression($info['Default']);
        } elseif (isset($type) && $type === 'bit') {
            $column->default_value = hexdec(trim($info['Default'], 'X\''));
        } else {
            $column->default_value = $column->php_typecast($info['Default']);
        }
        return $column;
    }
    /**
     * Determines the PDO type for the given PHP data value.
     * @param mixed $data the data whose PDO type is to be determined
     * @return int the PDO type
     * @see https://www.php.net/manual/en/pdo.constants.php
     */
    public function get_pdo_type($data)
    {
        static $type_map = [
            // php type => PDO type
            'boolean' => \PDO::PARAM_INT,
            // PARAM_BOOL is not supported by CUBRID PDO
            'integer' => \PDO::PARAM_INT,
            'string' => \PDO::PARAM_STR,
            'resource' => \PDO::PARAM_LOB,
            'NULL' => \PDO::PARAM_NULL,
        ];
        $type = gettype($data);
        return $type_map[$type] ?? \PDO::PARAM_STR;
    }
    /**
     * {@inheritdoc}
     * @see https://www.cubrid.org/manual/en/9.3.0/sql/transaction.html#database-concurrency
     */
    public function set_transaction_isolation_level($level): void
    {
        // translate SQL92 levels to CUBRID levels:
        switch ($level) {
            case Transaction::SERIALIZABLE:
                $level = '6';
                // SERIALIZABLE
                break;
            case Transaction::REPEATABLE_READ:
                $level = '5';
                // REPEATABLE READ CLASS with REPEATABLE READ INSTANCES
                break;
            case Transaction::READ_COMMITTED:
                $level = '4';
                // REPEATABLE READ CLASS with READ COMMITTED INSTANCES
                break;
            case Transaction::READ_UNCOMMITTED:
                $level = '3';
                // REPEATABLE READ CLASS with READ UNCOMMITTED INSTANCES
                break;
        }
        parent::set_transaction_isolation_level($level);
    }
    /**
     * {@inheritdoc}
     */
    public function create_column_schema_builder($type, $length = null): \yii\db\cubrid\Column_Schema_Builder
    {
        return new Column_Schema_Builder($type, $length, $this->db);
    }
    /**
     * Loads multiple types of constraints and returns the specified ones.
     * @param string $tableName table name.
     * @param string $returnType return type:
     * - indexes
     * - uniques
     * @return mixed constraints.
     */
    private function load_table_constraints($table_name, string $return_type)
    {
        $constraints = $this->db->get_slave_pdo(true)->cubrid_schema(\PDO::CUBRID_SCH_CONSTRAINT, $table_name);
        $constraints = Array_Helper::index($constraints, null, ['TYPE', 'NAME']);
        Array_Helper::multisort($constraints, 'KEY_ORDER', SORT_ASC, SORT_NUMERIC);
        $result = ['indexes' => [], 'uniques' => []];
        foreach ($constraints as $type => $names) {
            foreach ($names as $name => $constraint) {
                $is_unique = in_array((int) $type, [0, 2], true);
                $result['indexes'][] = new Index_Constraint(['isPrimary' => (bool) $constraint[0]['PRIMARY_KEY'], 'isUnique' => $is_unique, 'name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'ATTR_NAME')]);
                if ($is_unique) {
                    $result['uniques'][] = new Constraint(['name' => $name, 'columnNames' => Array_Helper::get_column($constraint, 'ATTR_NAME')]);
                }
            }
        }
        foreach ($result as $type => $data) {
            $this->set_table_metadata($table_name, $type, $data);
        }
        return $result[$return_type];
    }
}