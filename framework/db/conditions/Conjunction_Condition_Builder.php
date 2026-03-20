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
 * Class ConjunctionConditionBuilder builds objects of abstract class [[ConjunctionCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Conjunction_Condition_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|ConjunctionCondition $condition the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $condition, array &$params = []): string
    {
        $parts = $this->build_expressions_from($condition, $params);
        if (empty($parts)) {
            return '';
        }
        if (count($parts) === 1) {
            return reset($parts);
        }
        return '(' . implode(") {$condition->get_operator()} (", $parts) . ')';
    }
    /**
     * Builds expressions, that are stored in $condition
     *
     * @param ExpressionInterface|ConjunctionCondition $condition the expression to be built.
     * @param array $params the binding parameters.
     * @return string[]
     */
    private function build_expressions_from(Expression_Interface $condition, array &$params = []): array
    {
        $parts = [];
        foreach ($condition->get_expressions() as $condition) {
            if (is_array($condition)) {
                $condition = $this->query_builder->build_condition($condition, $params);
            }
            if ($condition instanceof Expression_Interface) {
                $condition = $this->query_builder->build_expression($condition, $params);
            }
            if ($condition !== '') {
                $parts[] = $condition;
            }
        }
        return $parts;
    }
}