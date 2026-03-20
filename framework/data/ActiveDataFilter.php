<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\data;

/**
 * ActiveDataFilter allows composing a filtering condition in a format suitable for [[\yii\db\QueryInterface::where()]].
 *
 * @see DataFilter
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.13
 */
class Active_Data_Filter extends Data_Filter
{
    /**
     * @var array maps filtering condition keywords to build methods.
     * These methods are used by [[buildCondition()]] to build the actual filtering conditions.
     * Particular condition builder can be specified using a PHP callback. For example:
     *
     * ```
     * [
     *     'XOR' => function (string $operator, mixed $condition) {
     *         //return array;
     *     },
     *     'LIKE' => function (string $operator, mixed $condition, string $attribute) {
     *         //return array;
     *     },
     * ]
     * ```
     */
    public $condition_builders = ['AND' => 'buildConjunctionCondition', 'OR' => 'buildConjunctionCondition', 'NOT' => 'buildBlockCondition', '<' => 'buildOperatorCondition', '>' => 'buildOperatorCondition', '<=' => 'buildOperatorCondition', '>=' => 'buildOperatorCondition', '=' => 'buildOperatorCondition', '!=' => 'buildOperatorCondition', 'IN' => 'buildOperatorCondition', 'NOT IN' => 'buildOperatorCondition', 'LIKE' => 'buildOperatorCondition'];
    /**
     * @var array map filtering operators to operators used in [[\yii\db\QueryInterface::where()]].
     * The format is: `[filterOperator => queryOperator]`.
     * If particular operator keyword does not appear in the map, it will be used as is.
     *
     * Usually the map can be left empty as filter operator names are consistent with the ones
     * used in [[\yii\db\QueryInterface::where()]]. However, you may want to adjust it in some special cases.
     * For example, when using PostgreSQL you may want to setup the following map:
     *
     * ```
     * [
     *     'LIKE' => 'ILIKE'
     * ]
     * ```
     */
    public $query_operator_map = [];
    /**
     * {@inheritdoc}
     */
    protected function build_internal()
    {
        $filter = $this->normalize(false);
        if (empty($filter)) {
            return [];
        }
        return $this->build_condition($filter);
    }
    /**
     * @param array $condition
     * @return array built condition.
     */
    protected function build_condition($condition)
    {
        $parts = [];
        foreach ($condition as $key => $value) {
            if (isset($this->condition_builders[$key])) {
                $method = $this->condition_builders[$key];
                if (is_string($method)) {
                    $callback = [$this, $method];
                } else {
                    $callback = $method;
                }
            } else {
                $callback = [$this, 'buildAttributeCondition'];
            }
            $parts[] = $callback($key, $value);
        }
        if (!empty($parts)) {
            if (count($parts) > 1) {
                array_unshift($parts, 'AND');
            } else {
                $parts = array_shift($parts);
            }
        }
        return $parts;
    }
    /**
     * Builds conjunction condition, which consists of multiple independent ones.
     * It covers such operators as `and` and `or`.
     * @param string $operator operator keyword.
     * @param mixed $condition raw condition.
     * @return array actual condition.
     */
    protected function build_conjunction_condition($operator, $condition): array
    {
        if (isset($this->query_operator_map[$operator])) {
            $operator = $this->query_operator_map[$operator];
        }
        $result = [$operator];
        foreach ($condition as $part) {
            $result[] = $this->build_condition($part);
        }
        return $result;
    }
    /**
     * Builds block condition, which consists of a single condition.
     * It covers such operators as `not`.
     * @param string $operator operator keyword.
     * @param mixed $condition raw condition.
     * @return array actual condition.
     */
    protected function build_block_condition($operator, $condition): array
    {
        if (isset($this->query_operator_map[$operator])) {
            $operator = $this->query_operator_map[$operator];
        }
        return [$operator, $this->build_condition($condition)];
    }
    /**
     * Builds search condition for a particular attribute.
     * @param string $attribute search attribute name.
     * @param mixed $condition search condition.
     * @return array actual condition.
     */
    protected function build_attribute_condition($attribute, $condition)
    {
        if (is_array($condition)) {
            $parts = [];
            foreach ($condition as $operator => $value) {
                if (isset($this->operator_types[$operator])) {
                    if (isset($this->condition_builders[$operator])) {
                        $method = $this->condition_builders[$operator];
                        if (is_string($method)) {
                            $callback = [$this, $method];
                        } else {
                            $callback = $method;
                        }
                        $parts[] = $callback($operator, $value, $attribute);
                    } else {
                        $parts[] = $this->build_operator_condition($operator, $value, $attribute);
                    }
                }
            }
            if (!empty($parts)) {
                if (count($parts) > 1) {
                    return array_merge(['AND'], $parts);
                }
                return array_shift($parts);
            }
        }
        return [$attribute => $this->filter_attribute_value($attribute, $condition)];
    }
    /**
     * Builds an operator condition.
     * @param string $operator operator keyword.
     * @param mixed $condition attribute condition.
     * @param string $attribute attribute name.
     * @return array actual condition.
     */
    protected function build_operator_condition($operator, $condition, $attribute): array
    {
        if (isset($this->query_operator_map[$operator])) {
            $operator = $this->query_operator_map[$operator];
        }
        return [$operator, $attribute, $this->filter_attribute_value($attribute, $condition)];
    }
}