<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use yii\base\Component;
/**
 * HtmlResponseFormatter formats the given data into an HTML response content.
 *
 * It is used by [[Response]] to format response data.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Html_Response_Formatter extends Component implements Response_Formatter_Interface
{
    /**
     * @var string the Content-Type header for the response
     */
    public $content_type = 'text/html';
    /**
     * Formats the specified response.
     * @param Response $response the response to be formatted.
     */
    public function format($response): void
    {
        if (stripos($this->content_type, 'charset') === false) {
            $this->content_type .= '; charset=' . $response->charset;
        }
        $response->get_headers()->set('Content-Type', $this->content_type);
        if ($response->data !== null) {
            $response->content = $response->data;
        }
    }
}