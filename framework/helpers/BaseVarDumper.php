<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\helpers;

use yii\base\Arrayable;
use yii\base\Invalid_Value_Exception;
/**
 * BaseVarDumper provides concrete implementation for [[VarDumper]].
 *
 * Do not use BaseVarDumper. Use [[VarDumper]] instead.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Base_Var_Dumper
{
    private static ?array $_objects = null;
    private static $_output;
    private static $_depth;
    /**
     * Displays a variable.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param int $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight whether the result should be syntax-highlighted
     */
    public static function dump($var, $depth = 10, $highlight = false): void
    {
        echo static::dump_as_string($var, $depth, $highlight);
    }
    /**
     * Dumps a variable in terms of a string.
     * This method achieves the similar functionality as var_dump and print_r
     * but is more robust when handling complex objects such as Yii controllers.
     * @param mixed $var variable to be dumped
     * @param int $depth maximum depth that the dumper should go into the variable. Defaults to 10.
     * @param bool $highlight whether the result should be syntax-highlighted
     * @return string the string representation of the variable
     */
    public static function dump_as_string($var, $depth = 10, $highlight = false)
    {
        self::$_output = '';
        self::$_objects = [];
        self::$_depth = $depth;
        self::dump_internal($var, 0);
        if ($highlight) {
            $result = highlight_string("<?php\n" . self::$_output, true);
            self::$_output = preg_replace('/&lt;\?php<br \/>/', '', $result, 1);
        }
        return self::$_output;
    }
    /**
     * @param mixed $var variable to be dumped
     * @param int $level depth level
     */
    private static function dump_internal($var, $level): void
    {
        switch (gettype($var)) {
            case 'boolean':
                self::$_output .= $var ? 'true' : 'false';
                break;
            case 'integer':
            case 'double':
                self::$_output .= (string) $var;
                break;
            case 'string':
                self::$_output .= "'" . addslashes($var) . "'";
                break;
            case 'resource':
                self::$_output .= '{resource}';
                break;
            case 'NULL':
                self::$_output .= 'null';
                break;
            case 'unknown type':
                self::$_output .= '{unknown}';
                break;
            case 'array':
                if (self::$_depth <= $level) {
                    self::$_output .= '[...]';
                } elseif (empty($var)) {
                    self::$_output .= '[]';
                } else {
                    $keys = array_keys($var);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output .= '[';
                    foreach ($keys as $key) {
                        self::$_output .= "\n" . $spaces . '    ';
                        self::dump_internal($key, 0);
                        self::$_output .= ' => ';
                        self::dump_internal($var[$key], $level + 1);
                    }
                    self::$_output .= "\n" . $spaces . ']';
                }
                break;
            case 'object':
                if (($id = array_search($var, self::$_objects, true)) !== false) {
                    self::$_output .= get_class($var) . '#' . ($id + 1) . '(...)';
                } elseif (self::$_depth <= $level) {
                    self::$_output .= get_class($var) . '(...)';
                } else {
                    $id = array_push(self::$_objects, $var);
                    $class_name = get_class($var);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output .= "{$class_name}#{$id}\n" . $spaces . '(';
                    if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__debugInfo')) {
                        $dump_values = $var->__debugInfo();
                        if (!is_array($dump_values)) {
                            throw new Invalid_Value_Exception('__debuginfo() must return an array');
                        }
                    } else {
                        $dump_values = (array) $var;
                    }
                    foreach ($dump_values as $key => $value) {
                        $key_display = strtr(trim($key), "\x00", ':');
                        self::$_output .= "\n" . $spaces . "    [{$key_display}] => ";
                        self::dump_internal($value, $level + 1);
                    }
                    self::$_output .= "\n" . $spaces . ')';
                }
                break;
        }
    }
    /**
     * Exports a variable as a string representation.
     *
     * The string is a valid PHP expression that can be evaluated by PHP parser
     * and the evaluation result will give back the variable value.
     *
     * This method is similar to `var_export()`. The main difference is that
     * it generates more compact string representation using short array syntax.
     *
     * It also handles objects by using the PHP functions serialize() and unserialize().
     *
     * PHP 5.4 or above is required to parse the exported value.
     *
     * @param mixed $var the variable to be exported.
     * @return string a string representation of the variable
     */
    public static function export($var): string
    {
        self::$_output = '';
        self::export_internal($var, 0);
        return self::$_output;
    }
    /**
     * @param mixed $var variable to be exported
     * @param int $level depth level
     */
    private static function export_internal($var, $level): void
    {
        switch (gettype($var)) {
            case 'NULL':
                self::$_output .= 'null';
                break;
            case 'array':
                if (empty($var)) {
                    self::$_output .= '[]';
                } else {
                    $keys = array_keys($var);
                    $output_keys = $keys !== range(0, count($var) - 1);
                    $spaces = str_repeat(' ', $level * 4);
                    self::$_output .= '[';
                    foreach ($keys as $key) {
                        self::$_output .= "\n" . $spaces . '    ';
                        if ($output_keys) {
                            self::export_internal($key, 0);
                            self::$_output .= ' => ';
                        }
                        self::export_internal($var[$key], $level + 1);
                        self::$_output .= ',';
                    }
                    self::$_output .= "\n" . $spaces . ']';
                }
                break;
            case 'object':
                if ($var instanceof \Closure) {
                    self::$_output .= self::export_closure($var);
                } else {
                    try {
                        $output = 'unserialize(' . var_export(serialize($var), true) . ')';
                    } catch (\Exception $e) {
                        // serialize may fail, for example: if object contains a `\Closure` instance
                        // so we use a fallback
                        if ($var instanceof Arrayable) {
                            self::export_internal($var->to_array(), $level);
                            return;
                        }
                        if ($var instanceof \IteratorAggregate) {
                            $var_as_array = [];
                            foreach ($var as $key => $value) {
                                $var_as_array[$key] = $value;
                            }
                            self::export_internal($var_as_array, $level);
                            return;
                        }
                        if ('__PHP_Incomplete_Class' !== get_class($var) && method_exists($var, '__toString')) {
                            $output = var_export($var->__toString(), true);
                        } else {
                            $output_backup = self::$_output;
                            $output = var_export(self::dump_as_string($var), true);
                            self::$_output = $output_backup;
                        }
                    }
                    self::$_output .= $output;
                }
                break;
            default:
                self::$_output .= var_export($var, true);
        }
    }
    /**
     * Exports a [[Closure]] instance.
     * @param \Closure $closure closure instance.
     */
    private static function export_closure(\Closure $closure): string
    {
        $reflection = new \ReflectionFunction($closure);
        $file_name = $reflection->get_file_name();
        $start = $reflection->get_start_line();
        $end = $reflection->get_end_line();
        if ($file_name === false || $start === false || $end === false) {
            return 'function() {/* Error: unable to determine Closure source */}';
        }
        --$start;
        $source = implode("\n", array_slice(file($file_name), $start, $end - $start));
        $tokens = token_get_all('<?php ' . $source);
        array_shift($tokens);
        $closure_tokens = [];
        $pending_parenthesis_count = 0;
        foreach ($tokens as $token) {
            if (isset($token[0]) && $token[0] === T_FUNCTION) {
                $closure_tokens[] = $token[1];
                continue;
            }
            if ($closure_tokens !== []) {
                $closure_tokens[] = $token[1] ?? $token;
                if ($token === '}') {
                    $pending_parenthesis_count--;
                    if ($pending_parenthesis_count === 0) {
                        break;
                    }
                } elseif ($token === '{') {
                    $pending_parenthesis_count++;
                }
            }
        }
        return implode('', $closure_tokens);
    }
}