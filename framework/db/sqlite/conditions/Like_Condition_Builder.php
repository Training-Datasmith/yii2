<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\sqlite\conditions;

/**
 * {@inheritdoc}
 */
class Like_Condition_Builder extends \yii\db\conditions\Like_Condition_Builder
{
    /**
     * {@inheritdoc}
     */
    protected $escape_character = '\\';
}