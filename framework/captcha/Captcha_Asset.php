<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\captcha;

use yii\web\Asset_Bundle;
/**
 * This asset bundle provides the javascript files needed for the [[Captcha]] widget.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Captcha_Asset extends Asset_Bundle
{
    public $source_path = '@yii/assets';
    public $js = ['yii.captcha.js'];
    public $depends = ['yii\web\YiiAsset'];
}