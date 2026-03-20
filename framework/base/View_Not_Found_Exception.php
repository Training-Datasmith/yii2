<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

/**
 * ViewNotFoundException represents an exception caused by view file not found.
 *
 * @author Alexander Makarov
 * @since 2.0.10
 */
class View_Not_Found_Exception extends InvalidArgumentException
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name(): string
    {
        return 'View not Found';
    }
}