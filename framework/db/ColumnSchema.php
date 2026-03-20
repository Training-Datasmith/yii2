<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\Base_Object;
use yii\helpers\String_Helper;
/**
 * ColumnSchema class describes the metadata of a column in a database table.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Column_Schema extends Base_Object
{
    /**
     * @var string name of this column (without quotes).
     */
    public $name;
    /**
     * @var bool whether this column can be null.
     */
    public $allow_null;
    /**
     * @var string abstract type of this column. Possible abstract types include:
     * char, string, text, boolean, smallint, integer, bigint, float, decimal, datetime,
     * timestamp, time, date, binary, and money.
     */
    public $type;
    /**
     * @var string the PHP type of this column. Possible PHP types include:
     * `string`, `boolean`, `integer`, `double`, `array`.
     */
    public $php_type;
    /**
     * @var string the DB type of this column. Possible DB types vary according to the type of DBMS.
     */
    public $db_type;
    /**
     * @var mixed default value of this column
     */
    public $default_value;
    /**
     * @var array enumerable values. This is set only if the column is declared to be an enumerable type.
     */
    public $enum_values;
    /**
     * @var int display size of the column.
     */
    public $size;
    /**
     * @var int precision of the column data, if it is numeric.
     */
    public $precision;
    /**
     * @var int scale of the column data, if it is numeric.
     */
    public $scale;
    /**
     * @var bool|null whether this column is a primary key
     */
    public $is_primary_key;
    /**
     * @var bool whether this column is auto-incremental
     */
    public $auto_increment = false;
    /**
     * @var bool whether this column is unsigned. This is only meaningful
     * when [[type]] is `smallint`, `integer` or `bigint`.
     */
    public $unsigned;
    /**
     * @var string comment of this column. Not all DBMS support this.
     */
    public $comment;
    /**
     * Converts the input value according to [[phpType]] after retrieval from the database.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value
     */
    public function php_typecast($value)
    {
        return $this->typecast($value);
    }
    /**
     * Converts the input value according to [[type]] and [[dbType]] for use in a db query.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value. This may also be an array containing the value as the first element
     * and the PDO type as the second element.
     */
    public function db_typecast($value)
    {
        // the default implementation does the same as casting for PHP, but it should be possible
        // to override this with annotation of explicit PDO type.
        return $this->typecast($value);
    }
    /**
     * Converts the input value according to [[phpType]] after retrieval from the database.
     * If the value is null or an [[Expression]], it will not be converted.
     * @param mixed $value input value
     * @return mixed converted value
     * @since 2.0.3
     */
    protected function typecast($value)
    {
        if ($value === '' && !in_array($this->type, [Schema::TYPE_TEXT, Schema::TYPE_STRING, Schema::TYPE_BINARY, Schema::TYPE_CHAR], true)) {
            return null;
        }
        if ($value === null || gettype($value) === $this->php_type || $value instanceof Expression_Interface || $value instanceof Query) {
            return $value;
        }
        if (is_array($value) && count($value) === 2 && isset($value[1]) && in_array($value[1], $this->get_pdo_param_types(), true)) {
            return new Pdo_Value($value[0], $value[1]);
        }
        switch ($this->php_type) {
            case 'resource':
            case 'string':
                if (is_resource($value)) {
                    return $value;
                }
                if (is_float($value)) {
                    // ensure type cast always has . as decimal separator in all locales
                    return String_Helper::float_to_string($value);
                }
                if (is_numeric($value) && Column_Schema_Builder::CATEGORY_NUMERIC === Column_Schema_Builder::$type_category_map[$this->type]) {
                    // https://github.com/yiisoft/yii2/issues/14663
                    return $value;
                }
                if (PHP_VERSION_ID >= 80100 && is_object($value) && $value instanceof \Backed_Enum) {
                    return (string) $value->value;
                }
                return (string) $value;
            case 'integer':
                if (PHP_VERSION_ID >= 80100 && is_object($value) && $value instanceof \Backed_Enum) {
                    return (int) $value->value;
                }
                return (int) $value;
            case 'boolean':
                // treating a 0 bit value as false too
                // https://github.com/yiisoft/yii2/issues/9006
                return (bool) $value && $value !== "\x00" && strtolower($value) !== 'false';
            case 'double':
                return (float) $value;
        }
        return $value;
    }
    /**
     * @return int[] array of numbers that represent possible PDO parameter types
     */
    private function get_pdo_param_types(): array
    {
        return [\PDO::PARAM_BOOL, \PDO::PARAM_INT, \PDO::PARAM_STR, \PDO::PARAM_LOB, \PDO::PARAM_NULL, \PDO::PARAM_STMT];
    }
}