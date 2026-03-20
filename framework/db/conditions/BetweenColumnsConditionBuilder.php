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
/**
 * Class BetweenColumnsConditionBuilder builds objects of [[BetweenColumnsCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Between_Columns_Condition_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|BetweenColumnsCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $expression, array &$params = []): string
    {
        $operator = $expression->get_operator();
        $start_column = $this->escape_column_name($expression->get_interval_start_column(), $params);
        $end_column = $this->escape_column_name($expression->get_interval_end_column(), $params);
        $value = $this->create_placeholder($expression->get_value(), $params);
        return "{$value} {$operator} {$start_column} AND {$end_column}";
    }
    /**
     * Prepares column name to be used in SQL statement.
     *
     * @param Query|ExpressionInterface|string $columnName
     * @param array $params the binding parameters.
     * @return string
     */
    protected function escape_column_name($column_name, &$params = [])
    {
        if ($column_name instanceof Query) {
            [$sql, $params] = $this->query_builder->build($column_name, $params);
            return "({$sql})";
        }
        if ($column_name instanceof Expression_Interface) {
            return $this->query_builder->build_expression($column_name, $params);
        }
        if (strpos($column_name, '(') === false) {
            return $this->query_builder->db->quote_column_name($column_name);
        }
        return $column_name;
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