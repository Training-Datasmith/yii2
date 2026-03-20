<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console;

use yii\base\User_Exception;
/**
 * Exception represents an exception caused by incorrect usage of a console command.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Exception extends User_Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name(): string
    {
        return 'Error';
    }
}