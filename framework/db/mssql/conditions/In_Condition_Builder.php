<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mssql\conditions;

use yii\base\Not_Supported_Exception;
use yii\db\Expression;
/**
 * {@inheritdoc}
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class In_Condition_Builder extends \yii\db\conditions\In_Condition_Builder
{
    /**
     * {@inheritdoc}
     * @throws NotSupportedException if `$columns` is an array
     */
    protected function build_subquery_in_condition($operator, $columns, \yii\db\Expression_Interface $values, &$params)
    {
        if (is_array($columns)) {
            throw new Not_Supported_Exception(__METHOD__ . ' is not supported by MSSQL.');
        }
        return parent::build_subquery_in_condition($operator, $columns, $values, $params);
    }
    /**
     * {@inheritdoc}
     */
    protected function build_composite_in_condition($operator, $columns, $values, &$params): string
    {
        $quoted_columns = [];
        foreach ($columns as $i => $column) {
            if ($column instanceof Expression) {
                $column = $column->expression;
            }
            $quoted_columns[$i] = strpos($column, '(') === false ? $this->query_builder->db->quote_column_name($column) : $column;
        }
        $vss = [];
        foreach ($values as $value) {
            $vs = [];
            foreach ($columns as $i => $column) {
                if ($column instanceof Expression) {
                    $column = $column->expression;
                }
                if (isset($value[$column])) {
                    $ph_name = $this->query_builder->bind_param($value[$column], $params);
                    $vs[] = $quoted_columns[$i] . ($operator === 'IN' ? ' = ' : ' != ') . $ph_name;
                } else {
                    $vs[] = $quoted_columns[$i] . ($operator === 'IN' ? ' IS' : ' IS NOT') . ' NULL';
                }
            }
            $vss[] = '(' . implode($operator === 'IN' ? ' AND ' : ' OR ', $vs) . ')';
        }
        return '(' . implode($operator === 'IN' ? ' OR ' : ' AND ', $vss) . ')';
    }
}