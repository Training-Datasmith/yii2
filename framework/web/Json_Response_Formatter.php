<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Component;
use yii\helpers\Json;
/**
 * JsonResponseFormatter formats the given data into a JSON or JSONP response content.
 *
 * It is used by [[Response]] to format response data.
 *
 * To configure properties like [[encodeOptions]] or [[prettyPrint]], you can configure the `response`
 * application component like the following:
 *
 * ```
 * 'response' => [
 *     // ...
 *     'formatters' => [
 *         \yii\web\Response::FORMAT_JSON => [
 *              'class' => 'yii\web\JsonResponseFormatter',
 *              'prettyPrint' => YII_DEBUG, // use "pretty" output in debug mode
 *              'keepObjectType' => false, // keep object type for zero-indexed objects
 *              // ...
 *         ],
 *     ],
 * ],
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Json_Response_Formatter extends Component implements Response_Formatter_Interface
{
    /**
     * JSON Content Type
     * @since 2.0.14
     */
    public const CONTENT_TYPE_JSONP = 'application/javascript; charset=UTF-8';
    /**
     * JSONP Content Type
     * @since 2.0.14
     */
    public const CONTENT_TYPE_JSON = 'application/json; charset=UTF-8';
    /**
     * HAL JSON Content Type
     * @since 2.0.14
     */
    public const CONTENT_TYPE_HAL_JSON = 'application/hal+json; charset=UTF-8';
    /**
     * @var string|null custom value of the `Content-Type` header of the response.
     * When equals `null` default content type will be used based on the `useJsonp` property.
     * @since 2.0.14
     */
    public $content_type;
    /**
     * @var bool whether to use JSONP response format. When this is true, the [[Response::data|response data]]
     * must be an array consisting of `data` and `callback` members. The latter should be a JavaScript
     * function name while the former will be passed to this function as a parameter.
     */
    public $use_jsonp = false;
    /**
     * @var int the encoding options passed to [[Json::encode()]]. For more details please refer to
     * <https://www.php.net/manual/en/function.json-encode.php>.
     * Default is `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
     * This property has no effect, when [[useJsonp]] is `true`.
     * @since 2.0.7
     */
    public $encode_options = 320;
    /**
     * @var bool whether to format the output in a readable "pretty" format. This can be useful for debugging purpose.
     * If this is true, `JSON_PRETTY_PRINT` will be added to [[encodeOptions]].
     * Defaults to `false`.
     * This property has no effect, when [[useJsonp]] is `true`.
     * @since 2.0.7
     */
    public $pretty_print = false;
    /**
     * @var bool Avoids objects with zero-indexed keys to be encoded as array
     * Json::encode((object)['test']) will be encoded as an object not array. This matches the behaviour of json_encode().
     * Defaults to Json::$keepObjectType value
     * @since 2.0.44
     */
    public $keep_object_type;
    /**
     * Formats the specified response.
     * @param Response $response the response to be formatted.
     */
    public function format($response): void
    {
        if ($this->content_type === null) {
            $this->content_type = $this->use_jsonp ? self::CONTENT_TYPE_JSONP : self::CONTENT_TYPE_JSON;
        } elseif (strpos($this->content_type, 'charset') === false) {
            $this->content_type .= '; charset=UTF-8';
        }
        $response->get_headers()->set('Content-Type', $this->content_type);
        if ($this->use_jsonp) {
            $this->format_jsonp($response);
        } else {
            $this->format_json($response);
        }
    }
    /**
     * Formats response data in JSON format.
     * @param Response $response
     */
    protected function format_json($response)
    {
        if ($response->data !== null) {
            $options = $this->encode_options;
            if ($this->pretty_print) {
                $options |= JSON_PRETTY_PRINT;
            }
            $default = Json::$keep_object_type;
            if ($this->keep_object_type !== null) {
                Json::$keep_object_type = $this->keep_object_type;
            }
            $response->content = Json::encode($response->data, $options);
            // Restore default value to avoid any unexpected behaviour
            Json::$keep_object_type = $default;
        } elseif ($response->content === null) {
            $response->content = 'null';
        }
    }
    /**
     * Formats response data in JSONP format.
     * @param Response $response
     */
    protected function format_jsonp($response)
    {
        if (is_array($response->data) && isset($response->data['data'], $response->data['callback'])) {
            $response->content = sprintf('%s(%s);', $response->data['callback'], Json::html_encode($response->data['data']));
        } elseif ($response->data !== null) {
            $response->content = '';
            Yii::warning("The 'jsonp' response requires that the data be an array consisting of both 'data' and 'callback' elements.", __METHOD__);
        }
    }
}