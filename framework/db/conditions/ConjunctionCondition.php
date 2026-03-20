<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\conditions;

/**
 * Class ConjunctionCondition
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
abstract class Conjunction_Condition implements Condition_Interface
{
    /**
     * @var mixed[]
     */
    protected $expressions;
    /**
     * @param mixed $expressions
     */
    public function __construct($expressions)
    {
        $this->expressions = $expressions;
    }
    /**
     * @return mixed[]
     */
    public function get_expressions()
    {
        return $this->expressions;
    }
    /**
     * Returns the operator that is represented by this condition class, e.g. `AND`, `OR`.
     * @return string
     */
    abstract public function get_operator();
    /**
     * {@inheritdoc}
     */
    public static function from_array_definition($operator, $operands)
    {
        return new static($operands);
    }
}