<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\conditions;

use yii\base\InvalidArgumentException;
use yii\db\Expression_Builder_Interface;
use yii\db\Expression_Builder_Trait;
use yii\db\Expression_Interface;
/**
 * Class LikeConditionBuilder builds objects of [[LikeCondition]]
 *
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 */
class Like_Condition_Builder implements Expression_Builder_Interface
{
    use Expression_Builder_Trait;
    /**
     * @var array map of chars to their replacements in LIKE conditions.
     * By default it's configured to escape `%`, `_` and `\` with `\`.
     */
    protected $escaping_replacements = ['%' => '\%', '_' => '\_', '\\' => '\\\\'];
    /**
     * @var string|null character used to escape special characters in LIKE conditions.
     * By default it's assumed to be `\`.
     */
    protected $escape_character;
    /**
     * Method builds the raw SQL from the $expression that will not be additionally
     * escaped or quoted.
     *
     * @param ExpressionInterface|LikeCondition $expression the expression to be built.
     * @param array $params the binding parameters.
     * @return string the raw SQL that will not be additionally escaped or quoted.
     */
    public function build(Expression_Interface $expression, array &$params = []): string
    {
        $operator = strtoupper($expression->get_operator());
        $column = $expression->get_column();
        $values = $expression->get_value();
        $escape = $expression->get_escaping_replacements();
        if ($escape === null || $escape === []) {
            $escape = $this->escaping_replacements;
        }
        [$andor, $not, $operator] = $this->parse_operator($operator);
        if (!is_array($values)) {
            $values = [$values];
        }
        if (empty($values)) {
            return $not ? '' : '0=1';
        }
        if ($column instanceof Expression_Interface) {
            $column = $this->query_builder->build_expression($column, $params);
        } elseif (is_string($column) && strpos($column, '(') === false) {
            $column = $this->query_builder->db->quote_column_name($column);
        }
        $escape_sql = $this->get_escape_sql();
        $parts = [];
        foreach ($values as $value) {
            if ($value instanceof Expression_Interface) {
                $ph_name = $this->query_builder->build_expression($value, $params);
            } else {
                $ph_name = $this->query_builder->bind_param(empty($escape) ? $value : '%' . strtr((string) $value, $escape) . '%', $params);
            }
            $parts[] = "{$column} {$operator} {$ph_name}{$escape_sql}";
        }
        return implode($andor, $parts);
    }
    private function get_escape_sql(): string
    {
        if ($this->escape_character !== null) {
            return " ESCAPE '{$this->escape_character}'";
        }
        return '';
    }
    /**
     * @param string $operator
     */
    protected function parse_operator($operator): array
    {
        if (!preg_match('/^(AND |OR |)(((NOT |))I?LIKE)/', $operator, $matches)) {
            throw new InvalidArgumentException("Invalid operator '{$operator}'.");
        }
        $andor = ' ' . (!empty($matches[1]) ? $matches[1] : 'AND ');
        $not = !empty($matches[3]);
        $operator = $matches[2];
        return [$andor, $not, $operator];
    }
}