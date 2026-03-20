<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\log;

use yii\helpers\Var_Dumper;
/**
 * SyslogTarget writes log to syslog.
 *
 * @author miramir <gmiramir@gmail.com>
 * @since 2.0
 */
class Syslog_Target extends Target
{
    /**
     * @var string syslog identity
     */
    public $identity;
    /**
     * @var int syslog facility.
     */
    public $facility = LOG_USER;
    /**
     * @var int|null openlog options. This is a bitfield passed as the `$option` parameter to [openlog()](https://www.php.net/openlog).
     * Defaults to `null` which means to use the default options `LOG_ODELAY | LOG_PID`.
     * @see https://www.php.net/openlog for available options.
     * @since 2.0.11
     */
    public $options;
    /**
     * @var array syslog levels
     */
    private array $_syslog_levels = [Logger::LEVEL_TRACE => LOG_DEBUG, Logger::LEVEL_PROFILE_BEGIN => LOG_DEBUG, Logger::LEVEL_PROFILE_END => LOG_DEBUG, Logger::LEVEL_PROFILE => LOG_DEBUG, Logger::LEVEL_INFO => LOG_INFO, Logger::LEVEL_WARNING => LOG_WARNING, Logger::LEVEL_ERROR => LOG_ERR];
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        parent::init();
        if ($this->options === null) {
            $this->options = LOG_ODELAY | LOG_PID;
        }
    }
    /**
     * Writes log messages to syslog.
     * Starting from version 2.0.14, this method throws LogRuntimeException in case the log can not be exported.
     * @throws LogRuntimeException
     */
    public function export(): void
    {
        openlog($this->identity, $this->options, $this->facility);
        foreach ($this->messages as $message) {
            if (syslog($this->_syslog_levels[$message[1]], $this->format_message($message)) === false) {
                throw new Log_Runtime_Exception('Unable to export log through system log!');
            }
        }
        closelog();
    }
    /**
     * {@inheritdoc}
     */
    public function format_message($message): string
    {
        [$text, $level, $category, $timestamp] = $message;
        $level = Logger::get_level_name($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Exception || $text instanceof \Throwable) {
                $text = (string) $text;
            } else {
                $text = Var_Dumper::export($text);
            }
        }
        $prefix = $this->get_message_prefix($message);
        return "{$prefix}[{$level}][{$category}] {$text}";
    }
}