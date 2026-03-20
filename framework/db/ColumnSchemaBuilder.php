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
 * ColumnSchemaBuilder helps to define database schema types using a PHP interface.
 *
 * See [[SchemaBuilderTrait]] for more detailed description and usage examples.
 *
 * @property array $categoryMap Mapping of abstract column types (keys) to type categories (values).
 *
 * @author Vasenin Matvey <vaseninm@gmail.com>
 * @since 2.0.6
 */
class Column_Schema_Builder extends Base_Object
{
    // Internally used constants representing categories that abstract column types fall under.
    // See [[$categoryMap]] for mappings of abstract column types to category.
    // @since 2.0.8
    public const CATEGORY_PK = 'pk';
    public const CATEGORY_STRING = 'string';
    public const CATEGORY_NUMERIC = 'numeric';
    public const CATEGORY_TIME = 'time';
    public const CATEGORY_OTHER = 'other';
    /**
     * @var string the column type definition such as INTEGER, VARCHAR, DATETIME, etc.
     */
    protected $type;
    /**
     * @var int|string|array column size or precision definition. This is what goes into the parenthesis after
     * the column type. This can be either a string, an integer or an array. If it is an array, the array values will
     * be joined into a string separated by comma.
     */
    protected $length;
    /**
     * @var bool|null whether the column is or not nullable. If this is `true`, a `NOT NULL` constraint will be added.
     * If this is `false`, a `NULL` constraint will be added.
     */
    protected $is_not_null;
    /**
     * @var bool whether the column values should be unique. If this is `true`, a `UNIQUE` constraint will be added.
     */
    protected $is_unique = false;
    /**
     * @var string the `CHECK` constraint for the column.
     */
    protected $check;
    /**
     * @var mixed default value of the column.
     */
    protected $default;
    /**
     * @var mixed SQL string to be appended to column schema definition.
     * @since 2.0.9
     */
    protected $append;
    /**
     * @var bool whether the column values should be unsigned. If this is `true`, an `UNSIGNED` keyword will be added.
     * @since 2.0.7
     */
    protected $is_unsigned = false;
    /**
     * @var string the column after which this column will be added.
     * @since 2.0.8
     */
    protected $after;
    /**
     * @var bool whether this column is to be inserted at the beginning of the table.
     * @since 2.0.8
     */
    protected $is_first;
    /**
     * @var array mapping of abstract column types (keys) to type categories (values).
     * @since 2.0.43
     */
    public static $type_category_map = [Schema::TYPE_PK => self::CATEGORY_PK, Schema::TYPE_UPK => self::CATEGORY_PK, Schema::TYPE_BIGPK => self::CATEGORY_PK, Schema::TYPE_UBIGPK => self::CATEGORY_PK, Schema::TYPE_CHAR => self::CATEGORY_STRING, Schema::TYPE_STRING => self::CATEGORY_STRING, Schema::TYPE_TEXT => self::CATEGORY_STRING, Schema::TYPE_TINYINT => self::CATEGORY_NUMERIC, Schema::TYPE_SMALLINT => self::CATEGORY_NUMERIC, Schema::TYPE_INTEGER => self::CATEGORY_NUMERIC, Schema::TYPE_BIGINT => self::CATEGORY_NUMERIC, Schema::TYPE_FLOAT => self::CATEGORY_NUMERIC, Schema::TYPE_DOUBLE => self::CATEGORY_NUMERIC, Schema::TYPE_DECIMAL => self::CATEGORY_NUMERIC, Schema::TYPE_DATETIME => self::CATEGORY_TIME, Schema::TYPE_TIMESTAMP => self::CATEGORY_TIME, Schema::TYPE_TIME => self::CATEGORY_TIME, Schema::TYPE_DATE => self::CATEGORY_TIME, Schema::TYPE_BINARY => self::CATEGORY_OTHER, Schema::TYPE_BOOLEAN => self::CATEGORY_NUMERIC, Schema::TYPE_MONEY => self::CATEGORY_NUMERIC];
    /**
     * @var \yii\db\Connection the current database connection. It is used mainly to escape strings
     * safely when building the final column schema string.
     * @since 2.0.8
     */
    public $db;
    /**
     * @var string comment value of the column.
     * @since 2.0.8
     */
    public $comment;
    /**
     * Create a column schema builder instance giving the type and value precision.
     *
     * @param string $type type of the column. See [[$type]].
     * @param int|string|array|null $length length or precision of the column. See [[$length]].
     * @param \yii\db\Connection|null $db the current database connection. See [[$db]].
     * @param array $config name-value pairs that will be used to initialize the object properties
     */
    public function __construct($type, $length = null, $db = null, $config = [])
    {
        $this->type = $type;
        $this->length = $length;
        $this->db = $db;
        parent::__construct($config);
    }
    /**
     * Adds a `NOT NULL` constraint to the column.
     * @return $this
     */
    public function not_null(): self
    {
        $this->is_not_null = true;
        return $this;
    }
    /**
     * Adds a `NULL` constraint to the column.
     * @return $this
     * @since 2.0.9
     */
    public function null(): self
    {
        $this->is_not_null = false;
        return $this;
    }
    /**
     * Adds a `UNIQUE` constraint to the column.
     * @return $this
     */
    public function unique(): self
    {
        $this->is_unique = true;
        return $this;
    }
    /**
     * Sets a `CHECK` constraint for the column.
     * @param string $check the SQL of the `CHECK` constraint to be added.
     * @return $this
     */
    public function check($check): self
    {
        $this->check = $check;
        return $this;
    }
    /**
     * Specify the default value for the column.
     * @param mixed $default the default value.
     * @return $this
     */
    public function default_value($default): self
    {
        if ($default === null) {
            $this->null();
        }
        $this->default = $default;
        return $this;
    }
    /**
     * Specifies the comment for column.
     * @param string $comment the comment
     * @return $this
     * @since 2.0.8
     */
    public function comment($comment): self
    {
        $this->comment = $comment;
        return $this;
    }
    /**
     * Marks column as unsigned.
     * @return $this
     * @since 2.0.7
     */
    public function unsigned(): self
    {
        switch ($this->type) {
            case Schema::TYPE_PK:
                $this->type = Schema::TYPE_UPK;
                break;
            case Schema::TYPE_BIGPK:
                $this->type = Schema::TYPE_UBIGPK;
                break;
        }
        $this->is_unsigned = true;
        return $this;
    }
    /**
     * Adds an `AFTER` constraint to the column.
     * Note: MySQL, Oracle and Cubrid support only.
     * @param string $after the column after which $this column will be added.
     * @return $this
     * @since 2.0.8
     */
    public function after($after): self
    {
        $this->after = $after;
        return $this;
    }
    /**
     * Adds an `FIRST` constraint to the column.
     * Note: MySQL, Oracle and Cubrid support only.
     * @return $this
     * @since 2.0.8
     */
    public function first(): self
    {
        $this->is_first = true;
        return $this;
    }
    /**
     * Specify the default SQL expression for the column.
     * @param string $default the default value expression.
     * @return $this
     * @since 2.0.7
     */
    public function default_expression($default): self
    {
        $this->default = new Expression($default);
        return $this;
    }
    /**
     * Specify additional SQL to be appended to column definition.
     * Position modifiers will be appended after column definition in databases that support them.
     * @param string $sql the SQL string to be appended.
     * @return $this
     * @since 2.0.9
     */
    public function append($sql): self
    {
        $this->append = $sql;
        return $this;
    }
    /**
     * Builds the full string for the column's schema.
     */
    public function __toString(): string
    {
        switch ($this->get_type_category()) {
            case self::CATEGORY_PK:
                $format = '{type}{check}{comment}{append}';
                break;
            default:
                $format = '{type}{length}{notnull}{unique}{default}{check}{comment}{append}';
        }
        return $this->build_complete_string($format);
    }
    /**
     * @return array mapping of abstract column types (keys) to type categories (values).
     * @since 2.0.43
     */
    public function get_category_map()
    {
        return static::$type_category_map;
    }
    /**
     * @param array $categoryMap mapping of abstract column types (keys) to type categories (values).
     * @since 2.0.43
     */
    public function set_category_map($category_map): void
    {
        static::$type_category_map = $category_map;
    }
    /**
     * Builds the length/precision part of the column.
     */
    protected function build_length_string(): string
    {
        if ($this->length === null || $this->length === []) {
            return '';
        }
        if (is_array($this->length)) {
            $this->length = implode(',', $this->length);
        }
        return "({$this->length})";
    }
    /**
     * Builds the not null constraint for the column.
     * @return string returns 'NOT NULL' if [[isNotNull]] is true,
     * 'NULL' if [[isNotNull]] is false or an empty string otherwise.
     */
    protected function build_not_null_string(): string
    {
        if ($this->is_not_null === true) {
            return ' NOT NULL';
        }
        if ($this->is_not_null === false) {
            return ' NULL';
        }
        return '';
    }
    /**
     * Builds the unique constraint for the column.
     * @return string returns string 'UNIQUE' if [[isUnique]] is true, otherwise it returns an empty string.
     */
    protected function build_unique_string(): string
    {
        return $this->is_unique ? ' UNIQUE' : '';
    }
    /**
     * Return the default value for the column.
     * @return string|null string with default value of column.
     */
    protected function build_default_value()
    {
        if ($this->default === null) {
            return $this->is_not_null === false ? 'NULL' : null;
        }
        switch (gettype($this->default)) {
            case 'double':
                // ensure type cast always has . as decimal separator in all locales
                $default_value = String_Helper::float_to_string($this->default);
                break;
            case 'boolean':
                $default_value = $this->default ? 'TRUE' : 'FALSE';
                break;
            case 'integer':
            case 'object':
                $default_value = (string) $this->default;
                break;
            default:
                $default_value = "'{$this->default}'";
        }
        return $default_value;
    }
    /**
     * Builds the default value specification for the column.
     * @return string string with default value of column.
     */
    protected function build_default_string(): string
    {
        $default_value = $this->build_default_value();
        if ($default_value === null) {
            return '';
        }
        return ' DEFAULT ' . $default_value;
    }
    /**
     * Builds the check constraint for the column.
     * @return string a string containing the CHECK constraint.
     */
    protected function build_check_string(): string
    {
        return $this->check !== null ? " CHECK ({$this->check})" : '';
    }
    /**
     * Builds the unsigned string for column. Defaults to unsupported.
     * @return string a string containing UNSIGNED keyword.
     * @since 2.0.7
     */
    protected function build_unsigned_string(): string
    {
        return '';
    }
    /**
     * Builds the after constraint for the column. Defaults to unsupported.
     * @return string a string containing the AFTER constraint.
     * @since 2.0.8
     */
    protected function build_after_string(): string
    {
        return '';
    }
    /**
     * Builds the first constraint for the column. Defaults to unsupported.
     * @return string a string containing the FIRST constraint.
     * @since 2.0.8
     */
    protected function build_first_string(): string
    {
        return '';
    }
    /**
     * Builds the custom string that's appended to column definition.
     * @return string custom string to append.
     * @since 2.0.9
     */
    protected function build_append_string(): string
    {
        return $this->append !== null ? ' ' . $this->append : '';
    }
    /**
     * Returns the category of the column type.
     * @return string a string containing the column type category name.
     * @since 2.0.8
     */
    protected function get_type_category()
    {
        return $this->category_map[$this->type] ?? null;
    }
    /**
     * Builds the comment specification for the column.
     * @return string a string containing the COMMENT keyword and the comment itself
     * @since 2.0.8
     */
    protected function build_comment_string(): string
    {
        return '';
    }
    /**
     * Returns the complete column definition from input format.
     * @param string $format the format of the definition.
     * @return string a string containing the complete column definition.
     * @since 2.0.8
     */
    protected function build_complete_string($format): string
    {
        $placeholder_values = ['{type}' => $this->type, '{length}' => $this->build_length_string(), '{unsigned}' => $this->build_unsigned_string(), '{notnull}' => $this->build_not_null_string(), '{unique}' => $this->build_unique_string(), '{default}' => $this->build_default_string(), '{check}' => $this->build_check_string(), '{comment}' => $this->build_comment_string(), '{pos}' => $this->is_first ? $this->build_first_string() : $this->build_after_string(), '{append}' => $this->build_append_string()];
        return strtr($format, $placeholder_values);
    }
}