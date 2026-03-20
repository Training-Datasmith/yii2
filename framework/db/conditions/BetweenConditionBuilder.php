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
 * Class BetweenConditionBuilder builds objects of [[BetweenCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Between_Condition_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|BetweenCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $expression, array &$params = []): string
    {
        $operator = $expression->get_operator();
        $column = $expression->get_column();
        if (strpos($column, '(') === false) {
            $column = $this->query_builder->db->quote_column_name($column);
        }
        $ph_name1 = $this->create_placeholder($expression->get_interval_start(), $params);
        $ph_name2 = $this->create_placeholder($expression->get_interval_end(), $params);
        return "{$column} {$operator} {$ph_name1} AND {$ph_name2}";
    }
    /**
     * Attaches $value to $params array and returns placeholder.
     *
     * @param mixed $value
     * @param array $params passed by reference
     * @return string
     */
    protected function create_placeholder($value, &$params)
    {
        if ($value instanceof Expression_Interface) {
            return $this->query_builder->build_expression($value, $params);
        }
        return $this->query_builder->bind_param($value, $params);
    }
}