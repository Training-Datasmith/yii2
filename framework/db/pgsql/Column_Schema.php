<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\pgsql;

use yii\db\Array_Expression;
use yii\db\Expression_Interface;
use yii\db\Json_Expression;
/**
 * Class ColumnSchema for PostgreSQL database.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 */
class Column_Schema extends \yii\db\Column_Schema
{
    /**
     * @var int the dimension of array. Defaults to 0, means this column is not an array.
     */
    public $dimension = 0;
    /**
     * @var bool whether the column schema should OMIT using JSON support feature.
     * You can use this property to make upgrade to Yii 2.0.14 easier.
     * Default to `false`, meaning JSON support is enabled.
     *
     * @since 2.0.14.1
     * @deprecated Since 2.0.14.1 and will be removed in 2.1.
     */
    public $disable_json_support = false;
    /**
     * @var bool whether the column schema should OMIT using PgSQL Arrays support feature.
     * You can use this property to make upgrade to Yii 2.0.14 easier.
     * Default to `false`, meaning Arrays support is enabled.
     *
     * @since 2.0.14.1
     * @deprecated Since 2.0.14.1 and will be removed in 2.1.
     */
    public $disable_array_support = false;
    /**
     * @var bool whether the Array column value should be unserialized to an [[ArrayExpression]] object.
     * You can use this property to make upgrade to Yii 2.0.14 easier.
     * Default to `true`, meaning arrays are unserialized to [[ArrayExpression]] objects.
     *
     * @since 2.0.14.1
     * @deprecated Since 2.0.14.1 and will be removed in 2.1.
     */
    public $deserialize_array_column_to_array_expression = true;
    /**
     * @var string name of associated sequence if column is auto-incremental
     * @since 2.0.29
     */
    public $sequence_name;
    /**
     * {@inheritdoc}
     */
    public function db_typecast($value)
    {
        if ($value === null) {
            return $value;
        }
        if ($value instanceof Expression_Interface) {
            return $value;
        }
        if ($this->dimension > 0) {
            return $this->disable_array_support ? (string) $value : new Array_Expression($value, $this->db_type, $this->dimension);
        }
        if (!$this->disable_json_support && in_array($this->db_type, [Schema::TYPE_JSON, Schema::TYPE_JSONB], true)) {
            return new Json_Expression($value, $this->db_type);
        }
        return $this->typecast($value);
    }
    /**
     * {@inheritdoc}
     */
    public function php_typecast($value)
    {
        if ($this->dimension > 0) {
            if ($this->disable_array_support) {
                return $value;
            }
            if (!is_array($value)) {
                $value = $this->get_array_parser()->parse($value);
            }
            if (is_array($value)) {
                array_walk_recursive($value, function (&$val, $key): void {
                    $val = $this->php_typecast_value($val);
                });
            } elseif ($value === null) {
                return null;
            }
            return $this->deserialize_array_column_to_array_expression ? new Array_Expression($value, $this->db_type, $this->dimension) : $value;
        }
        return $this->php_typecast_value($value);
    }
    /**
     * Casts $value after retrieving from the DBMS to PHP representation.
     *
     * @param string|null $value
     * @return bool|mixed|null
     */
    protected function php_typecast_value($value)
    {
        if ($value === null) {
            return null;
        }
        switch ($this->type) {
            case Schema::TYPE_BOOLEAN:
                switch (strtolower($value)) {
                    case 't':
                    case 'true':
                        return true;
                    case 'f':
                    case 'false':
                        return false;
                }
                return (bool) $value;
            case Schema::TYPE_JSON:
                return $this->disable_json_support ? $value : json_decode($value, true);
        }
        return parent::php_typecast($value);
    }
    /**
     * Creates instance of ArrayParser
     *
     * @return ArrayParser
     */
    protected function get_array_parser()
    {
        static $parser = null;
        if ($parser === null) {
            $parser = new Array_Parser();
        }
        return $parser;
    }
}