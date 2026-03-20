<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\db\pgsql;

/**
 * The class converts PostgreSQL array representation to PHP array
 *
 * @author Sergei Tigrov <rrr-r@ya.ru>
 * @author Dmytro Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0.14
 * @phpcs:disable Squiz.NamingConventions.ValidVariableName.PrivateNoUnderscore
 */
class Array_Parser
{
    /**
     * @var string Character used in array
     */
    private string $delimiter = ',';
    /**
     * Convert array from PostgreSQL to PHP
     *
     * @param string $value string to be converted
     * @return array|null
     */
    public function parse($value)
    {
        if ($value === null) {
            return null;
        }
        if ($value === '{}') {
            return [];
        }
        return $this->parse_array($value);
    }
    /**
     * Pares PgSQL array encoded in string
     *
     * @param string $value
     * @param int $i parse starting position
     */
    private function parse_array($value, &$i = 0): array
    {
        $result = [];
        $len = strlen($value);
        for (++$i; $i < $len; ++$i) {
            switch ($value[$i]) {
                case '{':
                    $result[] = $this->parse_array($value, $i);
                    break;
                case '}':
                    break 2;
                case $this->delimiter:
                    if (empty($result)) {
                        // `{}` case
                        $result[] = null;
                    }
                    if (in_array($value[$i + 1], [$this->delimiter, '}'], true)) {
                        // `{,}` case
                        $result[] = null;
                    }
                    break;
                default:
                    $result[] = $this->parse_string($value, $i);
            }
        }
        return $result;
    }
    /**
     * Parses PgSQL encoded string
     *
     * @param string $value
     * @param int $i parse starting position
     */
    private function parse_string($value, &$i): ?string
    {
        $is_quoted = $value[$i] === '"';
        $string_end_chars = $is_quoted ? ['"'] : [$this->delimiter, '}'];
        $result = '';
        $len = strlen($value);
        for ($i += $is_quoted ? 1 : 0; $i < $len; ++$i) {
            if (in_array($value[$i], ['\\', '"'], true) && in_array($value[$i + 1], [$value[$i], '"'], true)) {
                ++$i;
            } elseif (in_array($value[$i], $string_end_chars, true)) {
                break;
            }
            $result .= $value[$i];
        }
        $i -= $is_quoted ? 0 : 1;
        if (!$is_quoted && $result === 'NULL') {
            return null;
        }
        return $result;
    }
}