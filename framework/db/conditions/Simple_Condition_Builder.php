<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\conditions;

use yii\db\Expression_Builder_Interface;
use yii\db\Expression_Builder_Trait;
use yii\db\Expression_Interface;
/**
 * Class NotConditionBuilder builds objects of [[SimpleCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Simple_Condition_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|SimpleCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $expression, array &$params = []): string
    {
        $operator = $expression->get_operator();
        $column = $expression->get_column();
        $value = $expression->get_value();
        if ($column instanceof Expression_Interface) {
            $column = $this->query_builder->build_expression($column, $params);
        } elseif (is_string($column) && strpos($column, '(') === false) {
            $column = $this->query_builder->db->quote_column_name($column);
        }
        if ($value === null) {
            return "{$column} {$operator} NULL";
        }
        if ($value instanceof Expression_Interface) {
            return "{$column} {$operator} {$this->query_builder->build_expression($value, $params)}";
        }
        $ph_name = $this->query_builder->bind_param($value, $params);
        return "{$column} {$operator} {$ph_name}";
    }
}