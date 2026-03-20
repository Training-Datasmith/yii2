<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

/**
 * Class QueryExpressionBuilder is used internally to build [[Query]] object
 * using unified [[QueryBuilder]] expression building interface.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Query_Expression_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|Query $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $expression, array &$params = []): string
    {
        [$sql, $params] = $this->query_builder->build($expression, $params);
        return "({$sql})";
    }
}