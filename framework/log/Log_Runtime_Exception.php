<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\log;

/**
 * LogRuntimeException represents an exception caused by problems with log delivery.
 *
 * @author Bizley <pawel@positive.codes>
 * @since 2.0.14
 */
class Log_Runtime_Exception extends \yii\base\Exception
{
    /**
     * @return string the user-friendly name of this exception
     */
    public function get_name(): string
    {
        return 'Log Runtime';
    }
}