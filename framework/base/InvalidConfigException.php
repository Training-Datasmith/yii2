<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

/**
 * InvalidConfigException represents an exception caused by incorrect object configuration.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Invalid_Config_Exception extends Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name(): string
    {
        return 'Invalid Configuration';
    }
}