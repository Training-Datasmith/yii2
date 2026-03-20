<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mssql;

use yii\db\Column_Schema_Builder as AbstractColumnSchemaBuilder;
use yii\db\Expression;
/**
 * ColumnSchemaBuilder is the schema builder for MSSQL databases.
 *
 * @property-read string|null $checkValue The `CHECK` constraint for the column.
 * @property-read string|Expression|null $defaultValue Default value of the column.
 *
 * @author Valerii Gorbachev <darkdef@gmail.com>
 * @since 2.0.42
 */
class Column_Schema_Builder extends Abstract_Column_Schema_Builder
{
    protected $format = '{type}{length}{notnull}{unique}{default}{check}{append}';
    /**
     * Builds the full string for the column's schema.
     */
    public function __toString(): string
    {
        if ($this->get_type_category() === self::CATEGORY_PK) {
            $format = '{type}{check}{comment}{append}';
        } else {
            $format = $this->format;
        }
        return $this->build_complete_string($format);
    }
    /**
     * Changes default format string to MSSQL ALTER COMMAND.
     */
    public function set_alter_column_format(): void
    {
        $this->format = '{type}{length}{notnull}{append}';
    }
    /**
     * Getting the `Default` value for constraint
     * @return string|Expression|null default value of the column.
     */
    public function get_default_value()
    {
        if ($this->default instanceof Expression) {
            return $this->default;
        }
        return $this->build_default_value();
    }
    /**
     * Get the `Check` value for constraint
     * @return string|null the `CHECK` constraint for the column.
     */
    public function get_check_value(): ?string
    {
        return $this->check !== null ? (string) $this->check : null;
    }
    /**
     * @return bool whether the column values should be unique. If this is `true`, a `UNIQUE` constraint will be added.
     */
    public function is_unique()
    {
        return $this->is_unique;
    }
}