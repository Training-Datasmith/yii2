<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use yii\base\InvalidArgumentException;
use yii\helpers\Json;
/**
 * Parses a raw HTTP request using [[\yii\helpers\Json::decode()]].
 *
 * To enable parsing for JSON requests you can configure [[Request::parsers]] using this class:
 *
 * ```
 * 'request' => [
 *     'parsers' => [
 *         'application/json' => 'yii\web\JsonParser',
 *     ]
 * ]
 * ```
 *
 * @author Dan Schmidt <danschmidt5189@gmail.com>
 * @since 2.0
 */
class Json_Parser implements Request_Parser_Interface
{
    /**
     * @var bool whether to return objects in terms of associative arrays.
     */
    public $as_array = true;
    /**
     * @var bool whether to throw a [[BadRequestHttpException]] if the body is invalid JSON
     */
    public $throw_exception = true;
    /**
     * Parses a HTTP request body.
     * @param string $rawBody the raw HTTP request body.
     * @param string $contentType the content type specified for the request body.
     * @return array|\stdClass parameters parsed from the request body
     * @throws BadRequestHttpException if the body contains invalid json and [[throwException]] is `true`.
     */
    public function parse($raw_body, $content_type)
    {
        // converts JSONP to JSON
        if (strpos($content_type, 'application/javascript') !== false) {
            $raw_body = preg_filter('/(^[^{]+|[^}]+$)/', '', $raw_body);
        }
        try {
            $parameters = Json::decode($raw_body, $this->as_array);
            return $parameters ?? [];
        } catch (InvalidArgumentException $e) {
            if ($this->throw_exception) {
                throw new Bad_Request_Http_Exception('Invalid JSON data in request body: ' . $e->get_message());
            }
            return [];
        }
    }
}