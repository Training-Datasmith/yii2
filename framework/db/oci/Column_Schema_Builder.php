<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\oci;

use yii\db\Column_Schema_Builder as AbstractColumnSchemaBuilder;
/**
 * ColumnSchemaBuilder is the schema builder for Oracle databases.
 *
 * @author Vasenin Matvey <vaseninm@gmail.com>
 * @author Chris Harris <chris@buckshotsoftware.com>
 * @since 2.0.6
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
                $format = '{type}{length}{check}{append}';
                break;
            case self::CATEGORY_NUMERIC:
                $format = '{type}{length}{unsigned}{default}{notnull}{check}{append}';
                break;
            default:
                $format = '{type}{length}{default}{notnull}{check}{append}';
        }
        return $this->build_complete_string($format);
    }
}