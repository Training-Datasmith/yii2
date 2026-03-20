<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

/**
 * ForeignKeyConstraint represents the metadata of a table `FOREIGN KEY` constraint.
 *
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0.13
 */
class Foreign_Key_Constraint extends Constraint
{
    /**
     * @var string|null referenced table schema name.
     */
    public $foreign_schema_name;
    /**
     * @var string referenced table name.
     */
    public $foreign_table_name;
    /**
     * @var string[] list of referenced table column names.
     */
    public $foreign_column_names;
    /**
     * @var string|null referential action if rows in a referenced table are to be updated.
     */
    public $on_update;
    /**
     * @var string|null referential action if rows in a referenced table are to be deleted.
     */
    public $on_delete;
}