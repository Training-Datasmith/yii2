<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

/**
 * ExitException represents a normal termination of an application.
 *
 * Do not catch ExitException. Yii will handle this exception to terminate the application gracefully.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Exit_Exception extends \Exception
{
    /**
     * @var int the exit status code
     */
    public $status_code;
    /**
     * Constructor.
     * @param int $status the exit status code
     * @param string $message error message
     * @param int $code error code
     * @param \Throwable|null $previous The previous exception used for the exception chaining.
     */
    public function __construct($status = 0, $message = null, $code = 0, $previous = null)
    {
        $this->status_code = $status;
        if ($previous === null) {
            parent::__construct((string) $message, $code);
        } else {
            parent::__construct((string) $message, $code, $previous);
        }
    }
}