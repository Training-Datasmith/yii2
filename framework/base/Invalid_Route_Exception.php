<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

/**
 * InvalidRouteException represents an exception caused by an invalid route.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Invalid_Route_Exception extends User_Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name(): string
    {
        return 'Invalid Route';
    }
}