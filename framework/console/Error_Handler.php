<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\console;

use Yii;
use yii\base\ErrorException;
use yii\base\User_Exception;
use yii\helpers\Console;
/**
 * ErrorHandler handles uncaught PHP errors and exceptions.
 *
 * ErrorHandler is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->errorHandler`.
 *
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
class Error_Handler extends \yii\base\Error_Handler
{
    /**
     * Renders an exception using ansi format for console output.
     * @param \Throwable $exception the exception to be rendered.
     */
    protected function render_exception($exception)
    {
        $previous = $exception->get_previous();
        if ($exception instanceof Unknown_Command_Exception) {
            // display message and suggest alternatives in case of unknown command
            $message = $this->format_message($exception->get_name() . ': ') . $exception->command;
            $alternatives = $exception->get_suggested_alternatives();
            if (count($alternatives) === 1) {
                $message .= "\n\nDid you mean \"" . reset($alternatives) . '"?';
            } elseif (count($alternatives) > 1) {
                $message .= "\n\nDid you mean one of these?\n    - " . implode("\n    - ", $alternatives);
            }
        } elseif ($exception instanceof User_Exception && ($exception instanceof Exception || !YII_DEBUG)) {
            $message = $this->format_message($exception->get_name() . ': ') . $exception->get_message();
        } elseif (YII_DEBUG) {
            if ($exception instanceof Exception) {
                $message = $this->format_message("Exception ({$exception->get_name()})");
            } elseif ($exception instanceof ErrorException) {
                $message = $this->format_message($exception->get_name());
            } else {
                $message = $this->format_message('Exception');
            }
            $message .= $this->format_message(" '" . get_class($exception) . "'", [Console::BOLD, Console::FG_BLUE]) . ' with message ' . $this->format_message("'{$exception->get_message()}'", [Console::BOLD]) . "\n\nin " . dirname($exception->get_file()) . DIRECTORY_SEPARATOR . $this->format_message(basename($exception->get_file()), [Console::BOLD]) . ':' . $this->format_message($exception->get_line(), [Console::BOLD, Console::FG_YELLOW]) . "\n";
            if ($exception instanceof \yii\db\Exception && !empty($exception->error_info)) {
                $message .= "\n" . $this->format_message("Error Info:\n", [Console::BOLD]) . print_r($exception->error_info, true);
            }
            if ($previous === null) {
                $message .= "\n" . $this->format_message("Stack trace:\n", [Console::BOLD]) . $exception->get_trace_as_string();
            }
        } else {
            $message = $this->format_message('Error: ') . $exception->get_message();
        }
        if (PHP_SAPI === 'cli') {
            Console::stderr($message . "\n");
        } else {
            echo $message . "\n";
        }
        if ($previous !== null) {
            $caused_by = $this->format_message('Caused by: ', [Console::BOLD]);
            if (PHP_SAPI === 'cli') {
                Console::stderr($caused_by);
            } else {
                echo $caused_by;
            }
            $this->render_exception($previous);
        }
    }
    /**
     * Colorizes a message for console output.
     * @param string $message the message to colorize.
     * @param array $format the message format.
     * @return string the colorized message.
     * @see Console::ansiFormat() for details on how to specify the message format.
     */
    protected function format_message($message, $format = [Console::FG_RED, Console::BOLD])
    {
        $stream = PHP_SAPI === 'cli' ? \STDERR : \STDOUT;
        // try controller first to allow check for --color switch
        if (Yii::$app->controller instanceof \yii\console\Controller && Yii::$app->controller->is_color_enabled($stream) || Yii::$app instanceof \yii\console\Application && Console::stream_supports_ansi_colors($stream)) {
            return Console::ansi_format($message, $format);
        }
        return $message;
    }
}