<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mysql;

use yii\base\InvalidArgumentException;
use yii\caching\Cache_Interface;
use yii\caching\Db_Cache;
use yii\db\Exception;
use yii\db\Expression;
use yii\db\Query;
/**
 * QueryBuilder is the query builder for MySQL databases.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Query_Builder extends \yii\db\Query_Builder
{
    /**
     * @var array mapping from abstract column types (keys) to physical column types (values).
     */
    public $type_map = [Schema::TYPE_PK => 'int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_UPK => 'int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_BIGPK => 'bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_UBIGPK => 'bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', Schema::TYPE_CHAR => 'char(1)', Schema::TYPE_STRING => 'varchar(255)', Schema::TYPE_TEXT => 'text', Schema::TYPE_TINYINT => 'tinyint(3)', Schema::TYPE_SMALLINT => 'smallint(6)', Schema::TYPE_INTEGER => 'int(11)', Schema::TYPE_BIGINT => 'bigint(20)', Schema::TYPE_FLOAT => 'float', Schema::TYPE_DOUBLE => 'double', Schema::TYPE_DECIMAL => 'decimal(10,0)', Schema::TYPE_DATE => 'date', Schema::TYPE_BINARY => 'blob', Schema::TYPE_BOOLEAN => 'tinyint(1)', Schema::TYPE_MONEY => 'decimal(19,4)', Schema::TYPE_JSON => 'json'];
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        $this->type_map = array_merge($this->type_map, $this->default_time_type_map());
    }
    /**
     * {@inheritdoc}
     */
    protected function default_expression_builders()
    {
        return array_merge(parent::default_expression_builders(), ['yii\db\JsonExpression' => 'yii\db\mysql\JsonExpressionBuilder']);
    }
    /**
     * Builds a SQL statement for renaming a column.
     * @param string $table the table whose column is to be renamed. The name will be properly quoted by the method.
     * @param string $oldName the old name of the column. The name will be properly quoted by the method.
     * @param string $newName the new name of the column. The name will be properly quoted by the method.
     * @return string the SQL statement for renaming a DB column.
     * @throws Exception
     */
    public function rename_column($table, $old_name, $new_name)
    {
        $quoted_table = $this->db->quote_table_name($table);
        $row = $this->db->create_command('SHOW CREATE TABLE ' . $quoted_table)->query_one();
        if ($row === false) {
            throw new Exception("Unable to find column '{$old_name}' in table '{$table}'.");
        }
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }
        if (preg_match_all('/^\s*[`"](.*?)[`"]\s+(.*?),?$/m', $sql, $matches)) {
            foreach ($matches[1] as $i => $c) {
                if ($c === $old_name) {
                    return "ALTER TABLE {$quoted_table} CHANGE " . $this->db->quote_column_name($old_name) . ' ' . $this->db->quote_column_name($new_name) . ' ' . $matches[2][$i];
                }
            }
        }
        // try to give back a SQL anyway
        return "ALTER TABLE {$quoted_table} CHANGE " . $this->db->quote_column_name($old_name) . ' ' . $this->db->quote_column_name($new_name);
    }
    /**
     * {@inheritdoc}
     * @see https://bugs.mysql.com/bug.php?id=48875
     */
    public function create_index($name, $table, $columns, $unique = false)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ($unique ? ' ADD UNIQUE INDEX ' : ' ADD INDEX ') . $this->db->quote_table_name($name) . ' (' . $this->build_columns($columns) . ')';
    }
    /**
     * Builds a SQL statement for dropping a foreign key constraint.
     * @param string $name the name of the foreign key constraint to be dropped. The name will be properly quoted by the method.
     * @param string $table the table whose foreign is to be dropped. The name will be properly quoted by the method.
     * @return string the SQL statement for dropping a foreign key constraint.
     */
    public function drop_foreign_key($name, $table)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP FOREIGN KEY ' . $this->db->quote_column_name($name);
    }
    /**
     * Builds a SQL statement for removing a primary key constraint to an existing table.
     * @param string $name the name of the primary key constraint to be removed.
     * @param string $table the table that the primary key constraint will be removed from.
     * @return string the SQL statement for removing a primary key constraint from an existing table.
     */
    public function drop_primary_key($name, $table)
    {
        return 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' DROP PRIMARY KEY';
    }
    /**
     * {@inheritdoc}
     */
    public function drop_unique($name, $table)
    {
        return $this->drop_index($name, $table);
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
                $value = $this->db->create_command("SELECT MAX(`{$key}`) FROM {$table_name}")->query_scalar() + 1;
            } else {
                $value = (int) $value;
            }
            return "ALTER TABLE {$table_name} AUTO_INCREMENT={$value}";
        } elseif ($table === null) {
            throw new InvalidArgumentException("Table not found: {$table_name}");
        }
        throw new InvalidArgumentException("There is no sequence associated with table '{$table_name}'.");
    }
    /**
     * Builds a SQL statement for enabling or disabling integrity check.
     * @param bool $check whether to turn on or off the integrity check.
     * @param string $schema the schema of the tables. Meaningless for MySQL.
     * @param string $table the table name. Meaningless for MySQL.
     * @return string the SQL statement for checking integrity
     */
    public function check_integrity($check = true, $schema = '', $table = '')
    {
        return 'SET FOREIGN_KEY_CHECKS = ' . ($check ? 1 : 0);
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
            // limit is not optional in MySQL
            // https://stackoverflow.com/questions/255517/mysql-offset-infinite-rows/271650#271650
            // https://dev.mysql.com/doc/refman/5.7/en/select.html#idm46193796386608
            $sql = "LIMIT {$offset}, 18446744073709551615";
            // 2^64-1
        }
        return $sql;
    }
    /**
     * {@inheritdoc}
     */
    protected function has_limit($limit)
    {
        // In MySQL limit argument must be nonnegative integer constant
        return ctype_digit((string) $limit);
    }
    /**
     * {@inheritdoc}
     */
    protected function has_offset($offset)
    {
        // In MySQL offset argument must be nonnegative integer constant
        $offset = (string) $offset;
        return ctype_digit($offset) && $offset !== '0';
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
                if (!empty($table_schema->primary_key)) {
                    $columns = $table_schema->primary_key;
                    $default_value = 'NULL';
                } else {
                    $columns = [reset($table_schema->columns)->name];
                    $default_value = 'DEFAULT';
                }
                foreach ($columns as $name) {
                    $names[] = $this->db->quote_column_name($name);
                    $placeholders[] = $default_value;
                }
            }
        }
        return [$names, $placeholders, $values, $params];
    }
    /**
     * {@inheritdoc}
     * @see https://downloads.mysql.com/docs/refman-5.1-en.pdf
     */
    public function upsert($table, $insert_columns, $update_columns, &$params)
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
        if ($update_columns === true) {
            $update_columns = [];
            foreach ($update_names as $name) {
                $update_columns[$name] = new Expression('VALUES(' . $this->db->quote_column_name($name) . ')');
            }
        } elseif ($update_columns === false) {
            $name = $this->db->quote_column_name(reset($unique_names));
            $update_columns = [$name => new Expression($this->db->quote_table_name($table) . '.' . $name)];
        }
        list($updates, $params) = $this->prepare_update_sets($table, $update_columns, $params);
        return $insert_sql . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
    }
    /**
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function add_comment_on_column($table, $column, $comment)
    {
        // Strip existing comment which may include escaped quotes
        $definition = trim(preg_replace("/COMMENT '(?:''|[^'])*'/i", '', $this->get_column_definition($table, $column)));
        $check_regex = '/CHECK *(\(([^()]|(?-2))*\))/';
        $check = preg_match($check_regex, $definition, $check_matches);
        if ($check === 1) {
            $definition = preg_replace($check_regex, '', $definition);
        }
        $alter_sql = 'ALTER TABLE ' . $this->db->quote_table_name($table) . ' CHANGE ' . $this->db->quote_column_name($column) . ' ' . $this->db->quote_column_name($column) . (empty($definition) ? '' : ' ' . $definition) . ' COMMENT ' . $this->db->quote_value($comment);
        if ($check === 1) {
            $alter_sql .= ' ' . $check_matches[0];
        }
        return $alter_sql;
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
     * {@inheritdoc}
     * @since 2.0.8
     */
    public function select_exists($raw_sql)
    {
        return 'SELECT EXISTS(' . $raw_sql . ') AS ' . $this->db->quote_column_name('result');
    }
    /**
     * Gets column definition.
     *
     * @param string $table table name
     * @param string $column column name
     * @return string|null the column definition
     * @throws Exception in case when table does not contain column
     */
    private function get_column_definition($table, $column)
    {
        $quoted_table = $this->db->quote_table_name($table);
        $row = $this->db->create_command('SHOW CREATE TABLE ' . $quoted_table)->query_one();
        if ($row === false) {
            throw new Exception("Unable to find column '{$column}' in table '{$table}'.");
        }
        if (isset($row['Create Table'])) {
            $sql = $row['Create Table'];
        } else {
            $row = array_values($row);
            $sql = $row[1];
        }
        if (preg_match_all('/^\s*[`"](.*?)[`"]\s+(.*?),?$/m', $sql, $matches)) {
            foreach ($matches[1] as $i => $c) {
                if ($c === $column) {
                    return $matches[2][$i];
                }
            }
        }
        return null;
    }
    /**
     * Checks the ability to use fractional seconds.
     *
     * @return bool
     * @see https://dev.mysql.com/doc/refman/5.6/en/fractional-seconds.html
     */
    private function supports_fractional_seconds()
    {
        // use cache to prevent opening MySQL connection
        // https://github.com/yiisoft/yii2/issues/13749#issuecomment-481657224
        $key = [__METHOD__, $this->db->dsn];
        $cache = null;
        $schema_cache = \Yii::$app && is_string($this->db->schema_cache) ? \Yii::$app->get($this->db->schema_cache, false) : $this->db->schema_cache;
        // If the `$schemaCache` is an instance of `DbCache` we don't use it to avoid a loop
        if ($this->db->enable_schema_cache && $schema_cache instanceof Cache_Interface && !$schema_cache instanceof Db_Cache) {
            $cache = $schema_cache;
        }
        $version = $cache ? $cache->get($key) : null;
        if (!$version) {
            $version = $this->db->get_slave_pdo(true)->get_attribute(\PDO::ATTR_SERVER_VERSION);
            if ($cache) {
                $cache->set($key, $version, $this->db->schema_cache_duration);
            }
        }
        return version_compare($version, '5.6.4', '>=');
    }
    /**
     * Returns the map for default time type.
     * If the version of MySQL is lower than 5.6.4, then the types will be without fractional seconds,
     * otherwise with fractional seconds.
     *
     * @return array
     */
    private function default_time_type_map()
    {
        $map = [Schema::TYPE_DATETIME => 'datetime', Schema::TYPE_TIMESTAMP => 'timestamp', Schema::TYPE_TIME => 'time'];
        if ($this->supports_fractional_seconds()) {
            $map = [Schema::TYPE_DATETIME => 'datetime(0)', Schema::TYPE_TIMESTAMP => 'timestamp(0)', Schema::TYPE_TIME => 'time(0)'];
        }
        return $map;
    }
}