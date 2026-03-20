<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\conditions;

use yii\base\InvalidArgumentException;
/**
 * Class BetweenCondition represents a `BETWEEN` condition.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 * @phpcs:disable Squiz.NamingConventions.ValidVariableName.PrivateNoUnderscore
 */
class Between_Condition implements Condition_Interface
{
    /**
     * @var string $operator the operator to use (e.g. `BETWEEN` or `NOT BETWEEN`)
     */
    private $operator;
    /**
     * @var mixed the column name to the left of [[operator]]
     */
    private $column;
    /**
     * @var mixed beginning of the interval
     */
    private $interval_start;
    /**
     * @var mixed end of the interval
     */
    private $interval_end;
    /**
     * Creates a condition with the `BETWEEN` operator.
     *
     * @param mixed $column the literal to the left of $operator
     * @param string $operator the operator to use (e.g. `BETWEEN` or `NOT BETWEEN`)
     * @param mixed $intervalStart beginning of the interval
     * @param mixed $intervalEnd end of the interval
     */
    public function __construct($column, $operator, $interval_start, $interval_end)
    {
        $this->column = $column;
        $this->operator = $operator;
        $this->interval_start = $interval_start;
        $this->interval_end = $interval_end;
    }
    /**
     * @return string
     */
    public function get_operator()
    {
        return $this->operator;
    }
    /**
     * @return mixed
     */
    public function get_column()
    {
        return $this->column;
    }
    /**
     * @return mixed
     */
    public function get_interval_start()
    {
        return $this->interval_start;
    }
    /**
     * @return mixed
     */
    public function get_interval_end()
    {
        return $this->interval_end;
    }
    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException if wrong number of operands have been given.
     */
    public static function from_array_definition($operator, $operands): self
    {
        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new InvalidArgumentException("Operator '{$operator}' requires three operands.");
        }
        return new static($operands[0], $operator, $operands[1], $operands[2]);
    }
}