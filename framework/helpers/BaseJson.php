<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\helpers;

use yii\base\Arrayable;
use yii\base\InvalidArgumentException;
use yii\base\Model;
use yii\web\Js_Expression;
use yii\web\Json_Response_Formatter;
/**
 * BaseJson provides concrete implementation for [[Json]].
 *
 * Do not use BaseJson. Use [[Json]] instead.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Base_Json
{
    /**
     * @var bool|null Enables human readable output a.k.a. Pretty Print.
     * This can useful for debugging during development but is not recommended in a production environment!
     * In case `prettyPrint` is `null` (default) the `options` passed to `encode` functions will not be changed.
     * @since 2.0.43
     */
    public static $pretty_print;
    /**
     * @var bool Avoids objects with zero-indexed keys to be encoded as array
     * `Json::encode((object)['test'])` will be encoded as an object not as an array. This matches the behaviour of `json_encode()`.
     * Defaults to false to avoid any backwards compatibility issues.
     * Enable for single purpose: `Json::$keepObjectType = true;`
     * @see JsonResponseFormatter documentation to enable for all JSON responses
     * @since 2.0.44
     */
    public static $keep_object_type = false;
    /**
     * @var array List of JSON Error messages assigned to constant names for better handling of PHP <= 5.5.
     * @since 2.0.7
     */
    public static $json_error_messages = ['JSON_ERROR_SYNTAX' => 'Syntax error', 'JSON_ERROR_UNSUPPORTED_TYPE' => 'Type is not supported', 'JSON_ERROR_DEPTH' => 'The maximum stack depth has been exceeded', 'JSON_ERROR_STATE_MISMATCH' => 'Invalid or malformed JSON', 'JSON_ERROR_CTRL_CHAR' => 'Control character error, possibly incorrectly encoded', 'JSON_ERROR_UTF8' => 'Malformed UTF-8 characters, possibly incorrectly encoded'];
    /**
     * Encodes the given value into a JSON string.
     *
     * The method enhances `json_encode()` by supporting JavaScript expressions.
     * In particular, the method will not encode a JavaScript expression that is
     * represented in terms of a [[JsExpression]] object.
     *
     * Note that data encoded as JSON must be UTF-8 encoded according to the JSON specification.
     * You must ensure strings passed to this method have proper encoding before passing them.
     *
     * @param mixed $value the data to be encoded.
     * @param int $options the encoding options. For more details please refer to
     * <https://www.php.net/manual/en/function.json-encode.php>. Default is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
     * @return string the encoding result.
     * @throws InvalidArgumentException if there is any encoding error.
     */
    public static function encode($value, $options = 320)
    {
        $expressions = [];
        $value = static::process_data($value, $expressions, uniqid('', true));
        set_error_handler(function (): void {
            static::handle_json_error(JSON_ERROR_SYNTAX);
        }, E_WARNING);
        if (static::$pretty_print === true) {
            $options |= JSON_PRETTY_PRINT;
        } elseif (static::$pretty_print === false) {
            $options &= ~JSON_PRETTY_PRINT;
        }
        $json = json_encode($value, $options);
        restore_error_handler();
        static::handle_json_error(json_last_error());
        return $expressions === [] ? $json : strtr($json, $expressions);
    }
    /**
     * Encodes the given value into a JSON string HTML-escaping entities so it is safe to be embedded in HTML code.
     *
     * The method enhances `json_encode()` by supporting JavaScript expressions.
     * In particular, the method will not encode a JavaScript expression that is
     * represented in terms of a [[JsExpression]] object.
     *
     * Note that data encoded as JSON must be UTF-8 encoded according to the JSON specification.
     * You must ensure strings passed to this method have proper encoding before passing them.
     *
     * @param mixed $value the data to be encoded
     * @return string the encoding result
     * @since 2.0.4
     * @throws InvalidArgumentException if there is any encoding error
     */
    public static function html_encode($value)
    {
        return static::encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    }
    /**
     * Decodes the given JSON string into a PHP data structure.
     * @param string $json the JSON string to be decoded
     * @param bool $asArray whether to return objects in terms of associative arrays.
     * @return mixed the PHP data
     * @throws InvalidArgumentException if there is any decoding error
     */
    public static function decode($json, $as_array = true)
    {
        if (is_array($json)) {
            throw new InvalidArgumentException('Invalid JSON data.');
        }
        if ($json === null || $json === '') {
            return null;
        }
        $decode = json_decode((string) $json, $as_array);
        static::handle_json_error(json_last_error());
        return $decode;
    }
    /**
     * Handles [[encode()]] and [[decode()]] errors by throwing exceptions with the respective error message.
     *
     * @param int $lastError error code from [json_last_error()](https://www.php.net/manual/en/function.json-last-error.php).
     * @throws InvalidArgumentException if there is any encoding/decoding error.
     * @since 2.0.6
     */
    protected static function handle_json_error($last_error)
    {
        if ($last_error === JSON_ERROR_NONE) {
            return;
        }
        throw new InvalidArgumentException(json_last_error_msg(), $last_error);
    }
    /**
     * Pre-processes the data before sending it to `json_encode()`.
     * @param mixed $data the data to be processed
     * @param array $expressions collection of JavaScript expressions
     * @param string $expPrefix a prefix internally used to handle JS expressions
     * @return mixed the processed data
     */
    protected static function process_data($data, array &$expressions, $exp_prefix)
    {
        $revert_to_object = false;
        if (is_object($data)) {
            if ($data instanceof Js_Expression) {
                $token = "!{[{$exp_prefix}=" . count($expressions) . ']}!';
                $expressions['"' . $token . '"'] = $data->expression;
                return $token;
            }
            if ($data instanceof \JsonSerializable) {
                return static::process_data($data->jsonSerialize(), $expressions, $exp_prefix);
            }
            if ($data instanceof \DateTimeInterface) {
                return static::process_data((array) $data, $expressions, $exp_prefix);
            }
            if ($data instanceof Arrayable) {
                $data = $data->to_array();
            } elseif ($data instanceof \Generator) {
                $_data = [];
                foreach ($data as $name => $value) {
                    $_data[$name] = static::process_data($value, $expressions, $exp_prefix);
                }
                $data = $_data;
            } elseif ($data instanceof \Simple_Xml_Element) {
                $data = (array) $data;
                // Avoid empty elements to be returned as array.
                // Not breaking BC because empty array was always cast to stdClass before.
                $revert_to_object = true;
            } else {
                /*
                 * $data type is changed to array here and its elements will be processed further
                 * We must cast $data back to object later to keep intended dictionary type in JSON.
                 * Revert is only done when keepObjectType flag is provided to avoid breaking BC
                 */
                $revert_to_object = static::$keep_object_type;
                $result = [];
                foreach ($data as $name => $value) {
                    $result[$name] = $value;
                }
                $data = $result;
                // Avoid empty objects to be returned as array (would break BC without keepObjectType flag)
                if ($data === []) {
                    $revert_to_object = true;
                }
            }
        }
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = static::process_data($value, $expressions, $exp_prefix);
                }
            }
        }
        return $revert_to_object ? (object) $data : $data;
    }
    /**
     * Generates a summary of the validation errors.
     *
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param array $options the tag options in terms of name-value pairs. The following options are specially handled:
     *
     * - showAllErrors: boolean, if set to true every error message for each attribute will be shown otherwise
     *   only the first error message for each attribute will be shown. Defaults to `false`.
     *
     * @return string the generated error summary
     * @since 2.0.14
     */
    public static function error_summary($models, $options = [])
    {
        $show_all_errors = Array_Helper::remove($options, 'showAllErrors', false);
        $lines = self::collect_errors($models, $show_all_errors);
        return static::encode($lines);
    }
    /**
     * Return array of the validation errors.
     *
     * @param Model|Model[] $models the model(s) whose validation errors are to be displayed.
     * @param bool $showAllErrors if set to true every error message for each attribute will be shown otherwise
     * only the first error message for each attribute will be shown.
     * @return array of the validation errors
     * @since 2.0.14
     */
    private static function collect_errors($models, $show_all_errors): array
    {
        $lines = [];
        if (!is_array($models)) {
            $models = [$models];
        }
        foreach ($models as $model) {
            $lines[] = $model->get_error_summary($show_all_errors);
        }
        return array_unique(call_user_func_array('array_merge', $lines));
    }
}