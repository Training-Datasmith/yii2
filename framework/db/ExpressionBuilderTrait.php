<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db;

/**
 * Trait ExpressionBuilderTrait provides common constructor for classes that
 * should implement [[ExpressionBuilderInterface]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
trait Expression_Builder_Trait
{
    /**
     * @var QueryBuilder
     */
    protected $query_builder;
    /**
     * ExpressionBuilderTrait constructor.
     */
    public function __construct(Query_Builder $query_builder)
    {
        $this->query_builder = $query_builder;
    }
}