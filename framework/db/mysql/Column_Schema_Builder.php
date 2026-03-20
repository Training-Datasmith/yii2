<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mysql;

use yii\db\Column_Schema_Builder as AbstractColumnSchemaBuilder;
/**
 * ColumnSchemaBuilder is the schema builder for MySQL databases.
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
    protected function build_after_string(): string
    {
        return $this->after !== null ? ' AFTER ' . $this->db->quote_column_name($this->after) : '';
    }
    /**
     * {@inheritdoc}
     */
    protected function build_first_string(): string
    {
        return $this->is_first ? ' FIRST' : '';
    }
    /**
     * {@inheritdoc}
     */
    protected function build_comment_string(): string
    {
        return $this->comment !== null ? ' COMMENT ' . $this->db->quote_value($this->comment) : '';
    }
    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        switch ($this->get_type_category()) {
            case self::CATEGORY_PK:
                $format = '{type}{length}{comment}{check}{append}{pos}';
                break;
            case self::CATEGORY_NUMERIC:
                $format = '{type}{length}{unsigned}{notnull}{default}{unique}{comment}{append}{pos}{check}';
                break;
            default:
                $format = '{type}{length}{notnull}{default}{unique}{comment}{append}{pos}{check}';
        }
        return $this->build_complete_string($format);
    }
}