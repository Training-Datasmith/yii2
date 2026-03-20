<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\mysql;

use yii\db\Expression_Builder_Interface;
use yii\db\Expression_Builder_Trait;
use yii\db\Expression_Interface;
use yii\db\Json_Expression;
use yii\db\Query;
use yii\helpers\Json;
/**
 * Class JsonExpressionBuilder builds [[JsonExpression]] for MySQL DBMS.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Json_Expression_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    public const PARAM_PREFIX = ':qp';
    /**
     * {@inheritdoc}
     * @param JsonExpression|ExpressionInterface $expression the expression to be built
     */
    public function build(Expression_Interface $expression, array &$params = []): string
    {
        $value = $expression->get_value();
        if ($value instanceof Query) {
            [$sql, $params] = $this->query_builder->build($value, $params);
            return "({$sql})";
        }
        $placeholder = static::PARAM_PREFIX . count($params);
        $params[$placeholder] = Json::encode($value);
        return $placeholder;
    }
}