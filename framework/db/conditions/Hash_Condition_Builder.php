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
use yii\db\Query;
use yii\helpers\Array_Helper;
/**
 * Class HashConditionBuilder builds objects of [[HashCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Hash_Condition_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|HashCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $expression, array &$params = [])
    {
        $hash = $expression->get_hash();
        $parts = [];
        foreach ($hash as $column => $value) {
            if (Array_Helper::is_traversable($value) || $value instanceof Query) {
                // IN condition
                $parts[] = $this->query_builder->build_condition(new In_Condition($column, 'IN', $value), $params);
            } else {
                if (strpos($column, '(') === false) {
                    $column = $this->query_builder->db->quote_column_name($column);
                }
                if ($value === null) {
                    $parts[] = "{$column} IS NULL";
                } elseif ($value instanceof Expression_Interface) {
                    $parts[] = "{$column}=" . $this->query_builder->build_expression($value, $params);
                } else {
                    $ph_name = $this->query_builder->bind_param($value, $params);
                    $parts[] = "{$column}={$ph_name}";
                }
            }
        }
        return count($parts) === 1 ? $parts[0] : '(' . implode(') AND (', $parts) . ')';
    }
}