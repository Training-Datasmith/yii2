<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\sqlite;

use yii\db\Column_Schema_Builder as AbstractColumnSchemaBuilder;
/**
 * ColumnSchemaBuilder is the schema builder for Sqlite databases.
 *
 * @author Chris Harris <chris@buckshotsoftware.com>
 * @since 2.0.8
 */
class Column_Schema_Builder extends Abstract_Column_Schema_Builder
{
    /**
     * {@inheritdoc}
     */
    protected function build_unsigned_string(): string
    {
        return $this->is_unsigned ? ' UNSIGNED' : '';
    }
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        switch ($this->get_type_category()) {
            case self::CATEGORY_PK:
                $format = '{type}{check}{append}';
                break;
            case self::CATEGORY_NUMERIC:
                $format = '{type}{length}{unsigned}{notnull}{unique}{check}{default}{append}';
                break;
            default:
                $format = '{type}{length}{notnull}{unique}{check}{default}{append}';
        }
        return $this->build_complete_string($format);
    }
}