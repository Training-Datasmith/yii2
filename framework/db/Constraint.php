<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

use yii\base\Base_Object;
/**
 * Constraint represents the metadata of a table constraint.
 *
 * @author Sergey Makinen <sergey@makinen.ru>
 * @since 2.0.13
 */
class Constraint extends Base_Object
{
    /**
     * @var string[]|null list of column names the constraint belongs to.
     */
    public $column_names;
    /**
     * @var string|null the constraint name.
     */
    public $name;
}