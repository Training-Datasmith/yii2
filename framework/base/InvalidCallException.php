<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

/**
 * InvalidCallException represents an exception caused by calling a method in a wrong way.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Invalid_Call_Exception extends \BadMethodCallException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name(): string
    {
        return 'Invalid Call';
    }
}