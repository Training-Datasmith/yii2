<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

/**
 * This asset bundle provides the [jQuery](https://jquery.com/) JavaScript library.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Jquery_Asset extends Asset_Bundle
{
    public $source_path = '@bower/jquery/dist';
    public $js = ['jquery.js'];
}