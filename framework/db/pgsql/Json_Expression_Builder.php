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
use yii\helpers\Json;
/**
 * Class JsonExpressionBuilder builds [[JsonExpression]] for PostgreSQL DBMS.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Json_Expression_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * {@inheritdoc}
     * @param JsonExpression|ExpressionInterface $expression the expression to be built
     */
    public function build(Expression_Interface $expression, array &$params = []): string
    {
        $value = $expression->get_value();
        if ($value instanceof Query) {
            [$sql, $params] = $this->query_builder->build($value, $params);
            return "({$sql})" . $this->get_typecast($expression);
        }
        if ($value instanceof Array_Expression) {
            $placeholder = 'array_to_json(' . $this->query_builder->build_expression($value, $params) . ')';
        } else {
            $placeholder = $this->query_builder->bind_param(Json::encode($value), $params);
        }
        return $placeholder . $this->get_typecast($expression);
    }
    /**
     * @return string the typecast expression based on [[type]].
     */
    protected function get_typecast(Json_Expression $expression): string
    {
        if ($expression->get_type() === null) {
            return '';
        }
        return '::' . $expression->get_type();
    }
}