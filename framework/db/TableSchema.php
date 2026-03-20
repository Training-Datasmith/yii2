<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\Base_Object;
use yii\base\InvalidArgumentException;
/**
 * TableSchema represents the metadata of a database table.
 *
 * @property-read array $columnNames List of column names.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Table_Schema extends Base_Object
{
    /**
     * @var string the name of the schema that this table belongs to.
     */
    public $schema_name;
    /**
     * @var string the name of this table. The schema name is not included. Use [[fullName]] to get the name with schema name prefix.
     */
    public $name;
    /**
     * @var string the full name of this table, which includes the schema name prefix, if any.
     * Note that if the schema name is the same as the [[Schema::defaultSchema|default schema name]],
     * the schema name will not be included.
     */
    public $full_name;
    /**
     * @var string[] primary keys of this table.
     */
    public $primary_key = [];
    /**
     * @var string|null sequence name for the primary key. Null if no sequence.
     */
    public $sequence_name;
    /**
     * @var array foreign keys of this table. Each array element is of the following structure:
     *
     * ```
     * [
     *  'ForeignTableName',
     *  'fk1' => 'pk1',  // pk1 is in foreign table
     *  'fk2' => 'pk2',  // if composite foreign key
     * ]
     * ```
     */
    public $foreign_keys = [];
    /**
     * @var ColumnSchema[] column metadata of this table. Each array element is a [[ColumnSchema]] object, indexed by column names.
     */
    public $columns = [];
    /**
     * Gets the named column metadata.
     * This is a convenient method for retrieving a named column even if it does not exist.
     * @param string $name column name
     * @return ColumnSchema|null metadata of the named column. Null if the named column does not exist.
     */
    public function get_column($name)
    {
        return $this->columns[$name] ?? null;
    }
    /**
     * Returns the names of all columns in this table.
     * @return array list of column names
     */
    public function get_column_names(): array
    {
        return array_keys($this->columns);
    }
    /**
     * Manually specifies the primary key for this table.
     * @param string|array $keys the primary key (can be composite)
     * @throws InvalidArgumentException if the specified key cannot be found in the table.
     */
    public function fix_primary_key($keys): void
    {
        $keys = (array) $keys;
        $this->primary_key = $keys;
        foreach ($this->columns as $column) {
            $column->is_primary_key = false;
        }
        foreach ($keys as $key) {
            if (isset($this->columns[$key])) {
                $this->columns[$key]->is_primary_key = true;
            } else {
                throw new InvalidArgumentException("Primary key '{$key}' cannot be found in table '{$this->name}'.");
            }
        }
    }
}