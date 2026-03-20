<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\pgsql;

use yii\db\Array_Expression;
use yii\db\Expression_Builder_Interface;
use yii\db\Expression_Builder_Trait;
use yii\db\Expression_Interface;
use yii\db\Json_Expression;
use yii\db\Query;
/**
 * Class ArrayExpressionBuilder builds [[ArrayExpression]] for PostgreSQL DBMS.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Array_Expression_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * {@inheritdoc}
     * @param ArrayExpression|ExpressionInterface $expression the expression to be built
     */
    public function build(Expression_Interface $expression, array &$params = [])
    {
        $value = $expression->get_value();
        if ($value === null) {
            return 'NULL';
        }
        if ($value instanceof Query) {
            [$sql, $params] = $this->query_builder->build($value, $params);
            return $this->build_subquery_array($sql, $expression);
        }
        $placeholders = $this->build_placeholders($expression, $params);
        return 'ARRAY[' . implode(', ', $placeholders) . ']' . $this->get_typehint($expression);
    }
    /**
     * Builds placeholders array out of $expression values
     * @param array $params the binding parameters.
     */
    protected function build_placeholders(Expression_Interface $expression, &$params): array
    {
        $value = $expression->get_value();
        $placeholders = [];
        if ($value === null || !is_array($value) && !$value instanceof \Traversable) {
            return $placeholders;
        }
        if ($expression->get_dimension() > 1) {
            foreach ($value as $item) {
                $placeholders[] = $this->build($this->unnest_array_expression($expression, $item), $params);
            }
            return $placeholders;
        }
        foreach ($value as $item) {
            if ($item instanceof Query) {
                [$sql, $params] = $this->query_builder->build($item, $params);
                $placeholders[] = $this->build_subquery_array($sql, $expression);
                continue;
            }
            $item = $this->typecast_value($expression, $item);
            if ($item instanceof Expression_Interface) {
                $placeholders[] = $this->query_builder->build_expression($item, $params);
                continue;
            }
            $placeholders[] = $this->query_builder->bind_param($item, $params);
        }
        return $placeholders;
    }
    /**
     * @param mixed $value
     * @return ArrayExpression
     */
    private function unnest_array_expression(Array_Expression $expression, $value)
    {
        $expression_class = get_class($expression);
        return new $expression_class($value, $expression->get_type(), $expression->get_dimension() - 1);
    }
    /**
     * @return string the typecast expression based on [[type]].
     */
    protected function get_typehint(Array_Expression $expression): string
    {
        if ($expression->get_type() === null) {
            return '';
        }
        $result = '::' . $expression->get_type();
        return $result . str_repeat('[]', $expression->get_dimension());
    }
    /**
     * Build an array expression from a subquery SQL.
     *
     * @param string $sql the subquery SQL.
     * @return string the subquery array expression.
     */
    protected function build_subquery_array(string $sql, Array_Expression $expression): string
    {
        return 'ARRAY(' . $sql . ')' . $this->get_typehint($expression);
    }
    /**
     * Casts $value to use in $expression
     *
     * @param mixed $value
     * @return mixed
     */
    protected function typecast_value(Array_Expression $expression, $value)
    {
        if ($value instanceof Expression_Interface) {
            return $value;
        }
        if (in_array($expression->get_type(), [Schema::TYPE_JSON, Schema::TYPE_JSONB], true)) {
            return new Json_Expression($value);
        }
        return $value;
    }
}