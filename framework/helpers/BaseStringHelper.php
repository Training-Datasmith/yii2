<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\helpers;

use Yii;
/**
 * BaseStringHelper provides concrete implementation for [[StringHelper]].
 *
 * Do not use BaseStringHelper. Use [[StringHelper]] instead.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alex Makarov <sam@rmcreative.ru>
 * @since 2.0
 */
class Base_String_Helper
{
    /**
     * Returns the number of bytes in the given string.
     * This method ensures the string is treated as a byte array by using `mb_strlen()`.
     *
     * @param string $string the string being measured for length
     * @return int the number of bytes in the given string.
     */
    public static function byte_length($string): int
    {
        return mb_strlen((string) $string, '8bit');
    }
    /**
     * Returns the portion of string specified by the start and length parameters.
     * This method ensures the string is treated as a byte array by using `mb_substr()`.
     *
     * @param string $string the input string. Must be one character or longer.
     * @param int $start the starting position
     * @param int|null $length the desired portion length. If not specified or `null`, there will be
     * no limit on length i.e. the output will be until the end of the string.
     * @return string the extracted part of string, or FALSE on failure or an empty string.
     * @see https://www.php.net/manual/en/function.substr.php
     */
    public static function byte_substr($string, $start, $length = null): string
    {
        if ($length === null) {
            $length = static::byte_length($string);
        }
        return mb_substr((string) $string, $start, $length, '8bit');
    }
    /**
     * Converts php.ini style size to bytes.
     *
     * @param string $string php.ini style size. Examples: `512M`, `1024K`, `1G`, `256`.
     * @return int the number of bytes equivalent to the specified string.
     * @since 2.0.54
     */
    public static function convert_ini_size_to_bytes($string): int
    {
        switch (substr($string, -1)) {
            case 'M':
            case 'm':
                return (int) $string * 1048576;
            case 'K':
            case 'k':
                return (int) $string * 1024;
            case 'G':
            case 'g':
                return (int) $string * 1073741824;
            default:
                return (int) $string;
        }
    }
    /**
     * Returns the trailing name component of a path.
     * This method is similar to the php function `basename()` except that it will
     * treat both \ and / as directory separators, independent of the operating system.
     * This method was mainly created to work on php namespaces. When working with real
     * file paths, php's `basename()` should work fine for you.
     * Note: this method is not aware of the actual filesystem, or path components such as "..".
     *
     * @param string $path A path string.
     * @param string $suffix If the name component ends in suffix this will also be cut off.
     * @return string the trailing name component of the given path.
     * @see https://www.php.net/manual/en/function.basename.php
     */
    public static function basename($path, $suffix = ''): string
    {
        $path = (string) $path;
        $len = mb_strlen($suffix);
        if ($len > 0 && mb_substr($path, -$len) === $suffix) {
            $path = mb_substr($path, 0, -$len);
        }
        $path = rtrim(str_replace('\\', '/', $path), '/');
        $pos = mb_strrpos($path, '/');
        if ($pos !== false) {
            return mb_substr($path, $pos + 1);
        }
        return $path;
    }
    /**
     * Returns parent directory's path.
     * This method is similar to `dirname()` except that it will treat
     * both \ and / as directory separators, independent of the operating system.
     *
     * @param string $path A path string.
     * @return string the parent directory's path.
     * @see https://www.php.net/manual/en/function.basename.php
     */
    public static function dirname($path): string
    {
        $normalized_path = rtrim(str_replace('\\', '/', (string) $path), '/');
        $separator_position = mb_strrpos($normalized_path, '/');
        if ($separator_position !== false) {
            return mb_substr($path, 0, $separator_position);
        }
        return '';
    }
    /**
     * Truncates a string to the number of characters specified.
     *
     * In order to truncate for an exact length, the $suffix char length must be counted towards the $length. For example
     * to have a string which is exactly 255 long with $suffix `...` of 3 chars, then `StringHelper::truncate($string, 252, '...')`
     * must be used to ensure you have 255 long string afterwards.
     *
     * @param string $string The string to truncate.
     * @param int $length How many characters from original string to include into truncated string.
     * @param string $suffix String to append to the end of truncated string.
     * @param string|null $encoding The charset to use, defaults to charset currently used by application.
     * @param bool $asHtml Whether to treat the string being truncated as HTML and preserve proper HTML tags.
     * This parameter is available since version 2.0.1.
     * @return string the truncated string.
     */
    public static function truncate($string, $length, string $suffix = '...', $encoding = null, $as_html = false)
    {
        $string = (string) $string;
        if ($encoding === null) {
            $encoding = Yii::$app ? Yii::$app->charset : 'UTF-8';
        }
        if ($as_html) {
            return static::truncate_html($string, $length, $suffix, $encoding);
        }
        if (mb_strlen($string, $encoding) > $length) {
            return rtrim(mb_substr($string, 0, $length, $encoding)) . $suffix;
        }
        return $string;
    }
    /**
     * Truncates a string to the number of words specified.
     *
     * @param string $string The string to truncate.
     * @param int $count How many words from original string to include into truncated string.
     * @param string $suffix String to append to the end of truncated string.
     * @param bool $asHtml Whether to treat the string being truncated as HTML and preserve proper HTML tags.
     * This parameter is available since version 2.0.1.
     * @return string the truncated string.
     */
    public static function truncate_words($string, $count, string $suffix = '...', $as_html = false)
    {
        if ($as_html) {
            return static::truncate_html($string, $count, $suffix);
        }
        $words = preg_split('/(\s+)/u', trim($string), 0, PREG_SPLIT_DELIM_CAPTURE);
        if (count($words) / 2 > $count) {
            return implode('', array_slice($words, 0, $count * 2 - 1)) . $suffix;
        }
        return $string;
    }
    /**
     * Truncate a string while preserving the HTML.
     *
     * @param string $string The string to truncate
     * @param int $count The counter
     * @param string $suffix String to append to the end of the truncated string.
     * @param string|bool $encoding Encoding flag or charset.
     * @since 2.0.1
     */
    protected static function truncate_html($string, $count, $suffix, $encoding = false): string
    {
        $config = \Html_Purifier_config::create(null);
        if (Yii::$app !== null) {
            $config->set('Cache.SerializerPath', Yii::$app->get_runtime_path());
        }
        $lexer = \Html_Purifier_lexer::create($config);
        $tokens = $lexer->tokenize_html($string, $config, new \Html_Purifier_context());
        $open_tokens = [];
        $total_count = 0;
        $depth = 0;
        $truncated = [];
        foreach ($tokens as $token) {
            if ($token instanceof \Html_Purifier_token_start) {
                //Tag begins
                $open_tokens[$depth] = $token->name;
                $truncated[] = $token;
                ++$depth;
            } elseif ($token instanceof \Html_Purifier_token_text && $total_count <= $count) {
                //Text
                if (false === $encoding) {
                    preg_match('/^(\s*)/um', $token->data, $prefix_space) ?: $prefix_space = ['', ''];
                    $token->data = $prefix_space[1] . self::truncate_words(ltrim($token->data), $count - $total_count, '');
                    $current_count = self::count_words($token->data);
                } else {
                    $token->data = self::truncate($token->data, $count - $total_count, '', $encoding);
                    $current_count = mb_strlen($token->data, $encoding);
                }
                $total_count += $current_count;
                $truncated[] = $token;
            } elseif ($token instanceof \Html_Purifier_token_end) {
                //Tag ends
                if ($token->name === $open_tokens[$depth - 1]) {
                    --$depth;
                    unset($open_tokens[$depth]);
                    $truncated[] = $token;
                }
            } elseif ($token instanceof \Html_Purifier_token_empty) {
                //Self contained tags, i.e. <img/> etc.
                $truncated[] = $token;
            }
            if ($total_count >= $count) {
                if (0 < count($open_tokens)) {
                    krsort($open_tokens);
                    foreach ($open_tokens as $name) {
                        $truncated[] = new \Html_Purifier_token_end($name);
                    }
                }
                break;
            }
        }
        $context = new \Html_Purifier_context();
        $generator = new \Html_Purifier_generator($config, $context);
        return $generator->generate_from_tokens($truncated) . ($total_count >= $count ? $suffix : '');
    }
    /**
     * Check if given string starts with specified substring. Binary and multibyte safe.
     *
     * @param string $string Input string
     * @param string $with Part to search inside the $string
     * @param bool $caseSensitive Case sensitive search. Default is true. When case sensitive is enabled, `$with` must
     * exactly match the starting of the string in order to get a true value.
     * @return bool Returns true if first input starts with second input, false otherwise
     */
    public static function starts_with($string, $with, $case_sensitive = true)
    {
        $string = (string) $string;
        $with = (string) $with;
        if (!$bytes = static::byte_length($with)) {
            return true;
        }
        if ($case_sensitive) {
            return strncmp($string, $with, $bytes) === 0;
        }
        $encoding = Yii::$app ? Yii::$app->charset : 'UTF-8';
        $string = static::byte_substr($string, 0, $bytes);
        return mb_strtolower($string, $encoding) === mb_strtolower($with, $encoding);
    }
    /**
     * Check if given string ends with specified substring. Binary and multibyte safe.
     *
     * @param string $string Input string to check
     * @param string $with Part to search inside of the `$string`.
     * @param bool $caseSensitive Case sensitive search. Default is true. When case sensitive is enabled, `$with` must
     * exactly match the ending of the string in order to get a true value.
     * @return bool Returns true if first input ends with second input, false otherwise
     */
    public static function ends_with($string, $with, $case_sensitive = true)
    {
        $string = (string) $string;
        $with = (string) $with;
        if (!$bytes = static::byte_length($with)) {
            return true;
        }
        if ($case_sensitive) {
            // Warning check, see https://php.net/substr-compare#refsect1-function.substr-compare-returnvalues
            if (static::byte_length($string) < $bytes) {
                return false;
            }
            return substr_compare($string, $with, -$bytes, $bytes) === 0;
        }
        $encoding = Yii::$app ? Yii::$app->charset : 'UTF-8';
        $string = static::byte_substr($string, -$bytes);
        return mb_strtolower($string, $encoding) === mb_strtolower($with, $encoding);
    }
    /**
     * Explodes string into array, optionally trims values and skips empty ones.
     *
     * @param string $string String to be exploded.
     * @param string $delimiter Delimiter. Default is ','.
     * @param mixed $trim Whether to trim each element. Can be:
     *   - boolean - to trim normally;
     *   - string - custom characters to trim. Will be passed as a second argument to `trim()` function.
     *   - callable - will be called for each value instead of trim. Takes the only argument - value.
     * @param bool $skipEmpty Whether to skip empty strings between delimiters. Default is false.
     * @since 2.0.4
     */
    public static function explode($string, $delimiter = ',', $trim = true, $skip_empty = false): array
    {
        $result = explode($delimiter, $string);
        if ($trim !== false) {
            if ($trim === true) {
                $trim = 'trim';
            } elseif (!is_callable($trim)) {
                $trim = fn($v) => trim($v, $trim);
            }
            $result = array_map($trim, $result);
        }
        if ($skip_empty) {
            // Wrapped with array_values to make array keys sequential after empty values removing
            return array_values(array_filter($result, fn($value) => $value !== ''));
        }
        return $result;
    }
    /**
     * Counts words in a string.
     *
     * @param string $string the text to calculate
     * @since 2.0.8
     */
    public static function count_words($string): int
    {
        return count(preg_split('/\s+/u', $string, 0, PREG_SPLIT_NO_EMPTY));
    }
    /**
     * Returns string representation of number value with replaced commas to dots, if decimal point
     * of current locale is comma.
     *
     * @param int|float|string $value the value to normalize.
     * @since 2.0.11
     */
    public static function normalize_number($value): string
    {
        $value = (string) $value;
        $locale_info = localeconv();
        $decimal_separator = $locale_info['decimal_point'] ?? null;
        if ($decimal_separator !== null && $decimal_separator !== '.') {
            return str_replace($decimal_separator, '.', $value);
        }
        return $value;
    }
    /**
     * Encodes string into "Base 64 Encoding with URL and Filename Safe Alphabet" (RFC 4648).
     *
     * > Note: Base 64 padding `=` may be at the end of the returned string.
     * > `=` is not transparent to URL encoding.
     *
     * @param string $input the string to encode.
     * @return string encoded string.
     * @see https://tools.ietf.org/html/rfc4648#page-7
     * @since 2.0.12
     */
    public static function base64url_encode($input): string
    {
        return strtr(base64_encode($input), '+/', '-_');
    }
    /**
     * Decodes "Base 64 Encoding with URL and Filename Safe Alphabet" (RFC 4648).
     *
     * @param string $input encoded string.
     * @return string decoded string.
     * @see https://tools.ietf.org/html/rfc4648#page-7
     * @since 2.0.12
     */
    public static function base64url_decode($input): string
    {
        return base64_decode(strtr($input, '-_', '+/'));
    }
    /**
     * Safely casts a float to string independent of the current locale.
     * The decimal separator will always be `.`.
     *
     * @param float|int $number a floating point number or integer.
     * @return string the string representation of the number.
     * @since 2.0.13
     */
    public static function float_to_string($number): string
    {
        // . and , are the only decimal separators known in ICU data,
        // so its safe to call str_replace here
        return str_replace(',', '.', (string) $number);
    }
    /**
     * Checks if the passed string would match the given shell wildcard pattern.
     * This function emulates [[fnmatch()]], which may be unavailable at certain environment, using PCRE.
     *
     * @param string $pattern the shell wildcard pattern.
     * @param string $string the tested string.
     * @param array $options options for matching. Valid options are:
     *
     * - caseSensitive: bool, whether pattern should be case sensitive. Defaults to `true`.
     * - escape: bool, whether backslash escaping is enabled. Defaults to `true`.
     * - filePath: bool, whether slashes in string only matches slashes in the given pattern. Defaults to `false`.
     *
     * @return bool whether the string matches pattern or not.
     * @since 2.0.14
     */
    public static function match_wildcard($pattern, $string, array $options = [])
    {
        if ($pattern === '*' && empty($options['filePath'])) {
            return true;
        }
        $replacements = ['\\\\\\\\' => '\\\\', '\\\\\\*' => '[*]', '\\\\\\?' => '[?]', '\*' => '.*', '\?' => '.', '\[\!' => '[^', '\[' => '[', '\]' => ']', '\-' => '-'];
        if (isset($options['escape']) && !$options['escape']) {
            unset($replacements['\\\\\\\\']);
            unset($replacements['\\\\\\*']);
            unset($replacements['\\\\\\?']);
        }
        if (!empty($options['filePath'])) {
            $replacements['\*'] = '[^/\\\\]*';
            $replacements['\?'] = '[^/\\\\]';
        }
        $pattern = strtr(preg_quote($pattern, '#'), $replacements);
        $pattern = '#^' . $pattern . '$#us';
        if (isset($options['caseSensitive']) && !$options['caseSensitive']) {
            $pattern .= 'i';
        }
        return preg_match($pattern, (string) $string) === 1;
    }
    /**
     * This method provides a unicode-safe implementation of built-in PHP function `ucfirst()`.
     *
     * @param string $string the string to be proceeded
     * @param string $encoding Optional, defaults to "UTF-8"
     * @see https://www.php.net/manual/en/function.ucfirst.php
     * @since 2.0.16
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function mb_ucfirst($string, $encoding = 'UTF-8'): string
    {
        $first_char = mb_substr((string) $string, 0, 1, $encoding);
        $rest = mb_substr((string) $string, 1, null, $encoding);
        return mb_strtoupper($first_char, $encoding) . $rest;
    }
    /**
     * This method provides a unicode-safe implementation of built-in PHP function `ucwords()`.
     *
     * @param string $string the string to be proceeded
     * @param string $encoding Optional, defaults to "UTF-8"
     * @see https://www.php.net/manual/en/function.ucwords
     * @since 2.0.16
     * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
     */
    public static function mb_ucwords($string, $encoding = 'UTF-8'): string
    {
        $string = (string) $string;
        if (empty($string)) {
            return $string;
        }
        $parts = preg_split('/(\s+\W+\s+|^\W+\s+|\s+)/u', $string, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        $ucfirst_even = trim(mb_substr($parts[0], -1, 1, $encoding)) === '';
        foreach ($parts as $key => $value) {
            $is_even = (bool) ($key % 2);
            if ($ucfirst_even === $is_even) {
                $parts[$key] = static::mb_ucfirst($value, $encoding);
            }
        }
        return implode('', $parts);
    }
    /**
     * Masks a portion of a string with a repeated character.
     * This method is multibyte-safe.
     *
     * @param string $string The input string.
     * @param int $start The starting position from where to begin masking.
     * This can be a positive or negative integer.
     * Positive values count from the beginning,
     * negative values count from the end of the string.
     * @param int $length The length of the section to be masked.
     * The masking will start from the $start position
     * and continue for $length characters.
     * @param string $mask The character to use for masking. The default is '*'.
     * @return string The masked string.
     */
    public static function mask($string, $start, $length, $mask = '*')
    {
        $str_length = mb_strlen($string, 'UTF-8');
        // Return original string if start position is out of bounds
        if ($start >= $str_length || $start < -$str_length) {
            return $string;
        }
        $masked = mb_substr($string, 0, $start, 'UTF-8');
        $masked .= str_repeat($mask, abs($length));
        return $masked . mb_substr($string, $start + abs($length), null, 'UTF-8');
    }
    /**
     * Returns the portion of the string that lies between the first occurrence of the start string
     * and the last occurrence of the end string after that.
     *
     * @param string $string The input string.
     * @param string $start The string marking the start of the portion to extract.
     * @param string $end The string marking the end of the portion to extract.
     * @return string|null The portion of the string between the first occurrence of
     * start and the last occurrence of end, or null if either start or end cannot be found.
     */
    public static function find_between($string, $start, $end): ?string
    {
        $start_pos = mb_strpos($string, $start);
        if ($start_pos === false) {
            return null;
        }
        $start_pos += mb_strlen($start);
        $end_pos = mb_strrpos($string, $end, $start_pos);
        if ($end_pos === false) {
            return null;
        }
        return mb_substr($string, $start_pos, $end_pos - $start_pos);
    }
}