<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\conditions;

use yii\db\Expression;
use yii\db\Expression_Builder_Interface;
use yii\db\Expression_Builder_Trait;
use yii\db\Expression_Interface;
use yii\db\Query;
/**
 * Class InConditionBuilder builds objects of [[InCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class In_Condition_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
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
        $operator = strtoupper($expression->get_operator());
        $column = $expression->get_column();
        $values = $expression->get_values();
        if ($column === []) {
            // no columns to test against
            return $operator === 'IN' ? '0=1' : '';
        }
        if ($values instanceof Query) {
            return $this->build_subquery_in_condition($operator, $column, $values, $params);
        }
        if (!is_array($values) && !$values instanceof \Traversable) {
            // ensure values is an array
            $values = (array) $values;
        }
        if (is_array($column)) {
            if (count($column) > 1) {
                return $this->build_composite_in_condition($operator, $column, $values, $params);
            }
            $column = reset($column);
        }
        if ($column instanceof \Traversable) {
            if (iterator_count($column) > 1) {
                return $this->build_composite_in_condition($operator, $column, $values, $params);
            }
            $column->rewind();
            $column = $column->current();
        }
        if ($column instanceof Expression) {
            $column = $column->expression;
        }
        if (is_array($values)) {
            $raw_values = $values;
        } elseif ($values instanceof \Traversable) {
            $raw_values = $this->get_raw_values_from_traversable_object($values);
        }
        $null_condition = null;
        $null_condition_operator = null;
        if (isset($raw_values) && in_array(null, $raw_values, true)) {
            $null_condition = $this->get_null_condition($operator, $column);
            $null_condition_operator = $operator === 'IN' ? 'OR' : 'AND';
        }
        $sql_values = $this->build_values($expression, $values, $params);
        if (empty($sql_values)) {
            if ($null_condition === null) {
                return $operator === 'IN' ? '0=1' : '';
            }
            return $null_condition;
        }
        if (strpos($column, '(') === false) {
            $column = $this->query_builder->db->quote_column_name($column);
        }
        if (count($sql_values) > 1) {
            $sql = "{$column} {$operator} (" . implode(', ', $sql_values) . ')';
        } else {
            $operator = $operator === 'IN' ? '=' : '<>';
            $sql = $column . $operator . reset($sql_values);
        }
        return $null_condition !== null && $null_condition_operator !== null ? sprintf('%s %s %s', $sql, $null_condition_operator, $null_condition) : $sql;
    }
    /**
     * Builds $values to be used in [[InCondition]]
     *
     * @param array $values
     * @param array $params the binding parameters
     * @return array of prepared for SQL placeholders
     */
    protected function build_values(Condition_Interface $condition, $values, &$params): array
    {
        $sql_values = [];
        $column = $condition->get_column();
        if (is_array($column)) {
            $column = reset($column);
        }
        if ($column instanceof \Traversable) {
            $column->rewind();
            $column = $column->current();
        }
        if ($column instanceof Expression) {
            $column = $column->expression;
        }
        foreach ($values as $i => $value) {
            if (is_array($value) || $value instanceof \ArrayAccess) {
                $value = $value[$column] ?? null;
            }
            if ($value === null) {
                continue;
            }
            if ($value instanceof Expression_Interface) {
                $sql_values[$i] = $this->query_builder->build_expression($value, $params);
            } else {
                $sql_values[$i] = $this->query_builder->bind_param($value, $params);
            }
        }
        return $sql_values;
    }
    /**
     * Builds SQL for IN condition.
     *
     * @param string $operator
     * @param array|string $columns
     * @param Query $values
     * @param array $params
     * @return string SQL
     */
    protected function build_subquery_in_condition($operator, $columns, \yii\db\Expression_Interface $values, &$params): string
    {
        $sql = $this->query_builder->build_expression($values, $params);
        if (is_array($columns)) {
            foreach ($columns as $i => $col) {
                if ($col instanceof Expression) {
                    $col = $col->expression;
                }
                if (strpos($col, '(') === false) {
                    $columns[$i] = $this->query_builder->db->quote_column_name($col);
                }
            }
            return '(' . implode(', ', $columns) . ") {$operator} {$sql}";
        }
        if ($columns instanceof Expression) {
            $columns = $columns->expression;
        }
        if (strpos($columns, '(') === false) {
            $columns = $this->query_builder->db->quote_column_name($columns);
        }
        return "{$columns} {$operator} {$sql}";
    }
    /**
     * Builds SQL for IN condition.
     *
     * @param string $operator
     * @param array|\Traversable $columns
     * @param array $values
     * @param array $params
     * @return string SQL
     */
    protected function build_composite_in_condition($operator, $columns, $values, &$params): string
    {
        $vss = [];
        foreach ($values as $value) {
            $vs = [];
            foreach ($columns as $column) {
                if ($column instanceof Expression) {
                    $column = $column->expression;
                }
                if (isset($value[$column])) {
                    $vs[] = $this->query_builder->bind_param($value[$column], $params);
                } else {
                    $vs[] = 'NULL';
                }
            }
            $vss[] = '(' . implode(', ', $vs) . ')';
        }
        if (empty($vss)) {
            return $operator === 'IN' ? '0=1' : '';
        }
        $sql_columns = [];
        foreach ($columns as $column) {
            if ($column instanceof Expression) {
                $column = $column->expression;
            }
            $sql_columns[] = strpos($column, '(') === false ? $this->query_builder->db->quote_column_name($column) : $column;
        }
        return '(' . implode(', ', $sql_columns) . ") {$operator} (" . implode(', ', $vss) . ')';
    }
    /**
     * Builds is null/is not null condition for column based on operator
     *
     * @param string $operator
     * @param string $column
     * @return string is null or is not null condition
     * @since 2.0.31
     */
    protected function get_null_condition($operator, $column): string
    {
        $column = $this->query_builder->db->quote_column_name($column);
        if ($operator === 'IN') {
            return sprintf('%s IS NULL', $column);
        }
        return sprintf('%s IS NOT NULL', $column);
    }
    /**
     * @return array raw values
     * @since 2.0.31
     */
    protected function get_raw_values_from_traversable_object(\Traversable $traversable_object): array
    {
        $raw_values = [];
        foreach ($traversable_object as $value) {
            if (is_array($value)) {
                $values = array_values($value);
                $raw_values = array_merge($raw_values, $values);
            } else {
                $raw_values[] = $value;
            }
        }
        return $raw_values;
    }
}