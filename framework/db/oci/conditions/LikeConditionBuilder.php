<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\oci\conditions;

use yii\db\Expression_Interface;
/**
 * {@inheritdoc}
 */
class Like_Condition_Builder extends \yii\db\conditions\Like_Condition_Builder
{
    /**
     * {@inheritdoc}
     */
    protected $escape_character = '!';
    /**
     * `\` is initialized in [[buildLikeCondition()]] method since
     * we need to choose replacement value based on [[\yii\db\Schema::quoteValue()]].
     * {@inheritdoc}
     */
    protected $escaping_replacements = ['%' => '!%', '_' => '!_', '!' => '!!'];
    /**
     * {@inheritdoc}
     */
    public function build(Expression_Interface $expression, array &$params = [])
    {
        if (!isset($this->escaping_replacements['\\'])) {
            /*
             * Different pdo_oci8 versions may or may not implement PDO::quote(), so
             * yii\db\Schema::quoteValue() may or may not quote \.
             */
            $this->escaping_replacements['\\'] = substr($this->query_builder->db->quote_value('\\'), 1, -1);
        }
        return parent::build($expression, $params);
    }
}