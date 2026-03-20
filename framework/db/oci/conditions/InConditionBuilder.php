<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\oci\conditions;

use yii\db\conditions\In_Condition;
use yii\db\Expression_Interface;
/**
 * {@inheritdoc}
 */
class In_Condition_Builder extends \yii\db\conditions\In_Condition_Builder
{
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|InCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $expression, array &$params = [])
    {
        $split_condition = $this->split_condition($expression, $params);
        if ($split_condition !== null) {
            return $split_condition;
        }
        return parent::build($expression, $params);
    }
    /**
     * Oracle DBMS does not support more than 1000 parameters in `IN` condition.
     * This method splits long `IN` condition into series of smaller ones.
     *
     * @param ExpressionInterface|InCondition $condition the expression to be built.
     * @param array $params the binding parameters.
     * @return string|null null when split is not required. Otherwise - built SQL condition.
     */
    protected function split_condition(In_Condition $condition, &$params)
    {
        $operator = $condition->get_operator();
        $values = $condition->get_values();
        $column = $condition->get_column();
        if ($values instanceof \Traversable) {
            $values = iterator_to_array($values);
        }
        if (!is_array($values)) {
            return null;
        }
        $max_parameters = 1000;
        $count = count($values);
        if ($count <= $max_parameters) {
            return null;
        }
        $slices = [];
        for ($i = 0; $i < $count; $i += $max_parameters) {
            $slices[] = $this->query_builder->create_condition_from_array([$operator, $column, array_slice($values, $i, $max_parameters)]);
        }
        array_unshift($slices, $operator === 'IN' ? 'OR' : 'AND');
        return $this->query_builder->build_condition($slices, $params);
    }
}