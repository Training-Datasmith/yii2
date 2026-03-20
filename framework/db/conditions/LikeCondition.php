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
 * Class LikeCondition represents a `LIKE` condition.
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Like_Condition extends Simple_Condition
{
    /**
     * @var array|null|false map of chars to their replacements, `false` if characters should not be escaped
     * or either `null` or empty array if escaping is condition builder responsibility.
     * By default it's set to `null`.
     */
    protected $escaping_replacements;
    /**
     * This method allows to specify how to escape special characters in the value(s).
     *
     * @param array|null|false $escapingReplacements an array of mappings from the special characters to their escaped counterparts.
     * You may use `false` to indicate the values are already escaped and no escape should be applied,
     * or either `null` or empty array if escaping is condition builder responsibility.
     * Note that when using an escape mapping (or the third operand is not provided),
     * the values will be automatically enclosed within a pair of percentage characters.
     */
    public function set_escaping_replacements($escaping_replacements): void
    {
        $this->escaping_replacements = $escaping_replacements;
    }
    /**
     * @return array|null|false
     */
    public function get_escaping_replacements()
    {
        return $this->escaping_replacements;
    }
    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException if wrong number of operands have been given.
     */
    public static function from_array_definition($operator, $operands): self
    {
        if (!isset($operands[0], $operands[1])) {
            throw new InvalidArgumentException("Operator '{$operator}' requires two operands.");
        }
        $condition = new static($operands[0], $operator, $operands[1]);
        if (isset($operands[2])) {
            $condition->escaping_replacements = $operands[2];
        }
        return $condition;
    }
}