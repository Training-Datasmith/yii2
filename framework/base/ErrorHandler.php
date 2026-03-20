<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\base;

use Yii;
use yii\helpers\Var_Dumper;
use yii\web\Http_Exception;
/**
 * ErrorHandler handles uncaught PHP errors and exceptions.
 *
 * ErrorHandler is configured as an application component in [[\yii\base\Application]] by default.
 * You can access that instance via `Yii::$app->errorHandler`.
 *
 * For more details and usage information on ErrorHandler, see the [guide article on handling errors](guide:runtime-handling-errors).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Alexander Makarov <sam@rmcreative.ru>
 * @author Carsten Brandt <mail@cebe.cc>
 * @since 2.0
 */
abstract class Error_Handler extends Component
{
    /**
     * @event Event an event that is triggered when the handler is called by shutdown function via [[handleFatalError()]].
     * @since 2.0.46
     */
    public const EVENT_SHUTDOWN = 'shutdown';
    /**
     * @var bool whether to discard any existing page output before error display. Defaults to true.
     */
    public $discard_existing_output = true;
    /**
     * @var int the size of the reserved memory. A portion of memory is pre-allocated so that
     * when an out-of-memory issue occurs, the error handler is able to handle the error with
     * the help of this reserved memory. If you set this value to be 0, no memory will be reserved.
     * Defaults to 256KB.
     */
    public $memory_reserve_size = 262144;
    /**
     * @var \Throwable|null the exception that is being handled currently.
     */
    public $exception;
    /**
     * @var bool if true - `handleException()` will finish script with `ExitCode::OK`.
     * false - `ExitCode::UNSPECIFIED_ERROR`.
     * @since 2.0.36
     */
    public $silent_exit_on_exception;
    /**
     * @var \Throwable from HHVM error that stores backtrace
     */
    private ?\yii\base\ErrorException $_hhvm_exception = null;
    /**
     * @var bool whether this instance has been registered using `register()`
     */
    private bool $_registered = false;
    /**
     * @var string|null the current working directory
     */
    private $_working_directory;
    public function init(): void
    {
        $this->silent_exit_on_exception ??= YII_ENV_TEST;
        parent::init();
    }
    /**
     * Register this error handler.
     *
     * @since 2.0.32 this will not do anything if the error handler was already registered
     */
    public function register(): void
    {
        if (!$this->_registered) {
            ini_set('display_errors', false);
            set_exception_handler([$this, 'handleException']);
            if (defined('HHVM_VERSION')) {
                set_error_handler([$this, 'handleHhvmError']);
            } else {
                set_error_handler([$this, 'handleError']);
            }
            // to restore working directory in shutdown handler
            if (PHP_SAPI !== 'cli') {
                $this->_working_directory = getcwd();
            }
            register_shutdown_function([$this, 'handleFatalError']);
            $this->_registered = true;
        }
    }
    /**
     * Unregisters this error handler by restoring the PHP error and exception handlers.
     * @since 2.0.32 this will not do anything if the error handler was not registered
     */
    public function unregister(): void
    {
        if ($this->_registered) {
            $this->_working_directory = null;
            restore_error_handler();
            restore_exception_handler();
            $this->_registered = false;
        }
    }
    /**
     * Handles uncaught PHP exceptions.
     *
     * This method is implemented as a PHP exception handler.
     *
     * @param \Throwable $exception the exception that is not caught
     */
    public function handle_exception($exception): void
    {
        if ($exception instanceof Exit_Exception) {
            return;
        }
        $this->exception = $exception;
        // disable error capturing to avoid recursive errors while handling exceptions
        $this->unregister();
        // set preventive HTTP status code to 500 in case error handling somehow fails and headers are sent
        // HTTP exceptions will override this value in renderException()
        if (PHP_SAPI !== 'cli') {
            http_response_code(500);
        }
        try {
            $this->log_exception($exception);
            if ($this->discard_existing_output) {
                $this->clear_output();
            }
            $this->render_exception($exception);
            if (!$this->silent_exit_on_exception) {
                \Yii::get_logger()->flush(true);
                if (defined('HHVM_VERSION')) {
                    flush();
                }
                exit(1);
            }
        } catch (\Exception $e) {
            // an other exception could be thrown while displaying the exception
            $this->handle_fallback_exception_message($e, $exception);
        } catch (\Throwable $e) {
            // additional check for \Throwable introduced in PHP 7
            $this->handle_fallback_exception_message($e, $exception);
        }
        $this->exception = null;
    }
    /**
     * Handles exception thrown during exception processing in [[handleException()]].
     * @param \Throwable $exception Exception that was thrown during main exception processing.
     * @param \Throwable $previousException Main exception processed in [[handleException()]].
     * @since 2.0.11
     */
    protected function handle_fallback_exception_message($exception, $previous_exception)
    {
        $msg = "An Error occurred while handling another error:\n";
        $msg .= (string) $exception;
        $msg .= "\nPrevious exception:\n";
        $msg .= (string) $previous_exception;
        if (YII_DEBUG) {
            if (PHP_SAPI === 'cli') {
                echo $msg . "\n";
            } else {
                echo '<pre>' . htmlspecialchars($msg, ENT_QUOTES, Yii::$app->charset) . '</pre>';
            }
            $msg .= "\n\$_SERVER = " . Var_Dumper::export($_SERVER);
        } else {
            echo 'An internal server error occurred.';
        }
        error_log($msg);
        if (defined('HHVM_VERSION')) {
            flush();
        }
        exit(1);
    }
    /**
     * Handles HHVM execution errors such as warnings and notices.
     *
     * This method is used as a HHVM error handler. It will store exception that will
     * be used in fatal error handler
     *
     * @param int $code the level of the error raised.
     * @param string $message the error message.
     * @param string $file the filename that the error was raised in.
     * @param int $line the line number the error was raised at.
     * @param mixed $context
     * @param mixed $backtrace trace of error
     * @return bool whether the normal error handler continues.
     *
     * @throws ErrorException
     * @since 2.0.6
     */
    public function handle_hhvm_error($code, $message, $file, $line, $context, $backtrace)
    {
        if ($this->handle_error($code, $message, $file, $line)) {
            return true;
        }
        if (E_ERROR & $code) {
            $exception = new ErrorException($message, $code, $code, $file, $line);
            $ref = new \ReflectionProperty('\Exception', 'trace');
            // @link https://wiki.php.net/rfc/deprecations_php_8_5#deprecate_reflectionsetaccessible
            // @link https://wiki.php.net/rfc/make-reflection-setaccessible-no-op
            if (PHP_VERSION_ID < 80100) {
                $ref->set_accessible(true);
            }
            $ref->set_value($exception, $backtrace);
            $this->_hhvm_exception = $exception;
        }
        return false;
    }
    /**
     * Handles PHP execution errors such as warnings and notices.
     *
     * This method is used as a PHP error handler. It will simply raise an [[ErrorException]].
     *
     * @param int $code the level of the error raised.
     * @param string $message the error message.
     * @param string $file the filename that the error was raised in.
     * @param int $line the line number the error was raised at.
     * @return bool whether the normal error handler continues.
     *
     * @throws ErrorException
     */
    public function handle_error($code, $message, $file, $line)
    {
        if (error_reporting() & $code) {
            // load ErrorException manually here because autoloading them will not work
            // when error occurs while autoloading a class
            if (!class_exists('yii\base\ErrorException', false)) {
                require_once __DIR__ . '/ErrorException.php';
            }
            $exception = new ErrorException($message, $code, $code, $file, $line);
            throw $exception;
        }
        return false;
    }
    /**
     * Handles fatal PHP errors.
     */
    public function handle_fatal_error(): void
    {
        if (!empty($this->_working_directory)) {
            // fix working directory for some Web servers e.g. Apache
            chdir($this->_working_directory);
            // flush memory
            $this->_working_directory = null;
        }
        $error = error_get_last();
        if ($error === null) {
            return;
        }
        // load ErrorException manually here because autoloading them will not work
        // when error occurs while autoloading a class
        if (!class_exists('yii\base\ErrorException', false)) {
            require_once __DIR__ . '/ErrorException.php';
        }
        if (!ErrorException::is_fatal_error($error)) {
            return;
        }
        if (!empty($this->_hhvm_exception)) {
            $this->exception = $this->_hhvm_exception;
        } else {
            $this->exception = new ErrorException($error['message'], $error['type'], $error['type'], $error['file'], $error['line']);
        }
        unset($error);
        $this->log_exception($this->exception);
        if ($this->discard_existing_output) {
            $this->clear_output();
        }
        $this->render_exception($this->exception);
        // need to explicitly flush logs because exit() next will terminate the app immediately
        Yii::get_logger()->flush(true);
        if (defined('HHVM_VERSION')) {
            flush();
        }
        $this->trigger(static::EVENT_SHUTDOWN);
        // ensure it is called after user-defined shutdown functions
        register_shutdown_function(function (): void {
            exit(1);
        });
    }
    /**
     * Renders the exception.
     * @param \Throwable $exception the exception to be rendered.
     */
    abstract protected function render_exception($exception);
    /**
     * Logs the given exception.
     * @param \Throwable $exception the exception to be logged
     * @since 2.0.3 this method is now public.
     */
    public function log_exception($exception): void
    {
        $category = get_class($exception);
        if ($exception instanceof Http_Exception) {
            $category = 'yii\web\HttpException:' . $exception->status_code;
        } elseif ($exception instanceof \ErrorException) {
            $category .= ':' . $exception->get_severity();
        }
        Yii::error($exception, $category);
    }
    /**
     * Removes all output echoed before calling this method.
     */
    public function clear_output(): void
    {
        // the following manual level counting is to deal with zlib.output_compression set to On
        for ($level = ob_get_level(); $level > 0; --$level) {
            if (!@ob_end_clean()) {
                ob_clean();
            }
        }
    }
    /**
     * Converts an exception into a PHP error.
     *
     * This method can be used to convert exceptions inside of methods like `__toString()`
     * to PHP errors because exceptions cannot be thrown inside of them.
     * @param \Throwable $exception the exception to convert to a PHP error.
     * @return never
     *
     * @deprecated since 2.0.53. Use conditional exception throwing in `__toString()` methods instead.
     * For PHP < 7.4: use `trigger_error()` directly with `convertExceptionToString()` method.
     * For PHP >= 7.4: throw the exception directly as `__toString()` supports exceptions.
     * This method will be removed in 2.2.0.
     */
    public static function convert_exception_to_error($exception): void
    {
        trigger_error(static::convert_exception_to_string($exception), E_USER_ERROR);
    }
    /**
     * Converts an exception into a simple string.
     * @param \Throwable $exception the exception being converted
     * @return string the string representation of the exception.
     */
    public static function convert_exception_to_string($exception)
    {
        if ($exception instanceof User_Exception) {
            return "{$exception->get_name()}: {$exception->get_message()}";
        }
        return static::convert_exception_to_verbose_string($exception);
    }
    /**
     * Converts an exception into a string that has verbose information about the exception and its trace.
     * @param \Throwable $exception the exception being converted
     * @return string the string representation of the exception.
     *
     * @since 2.0.14
     */
    public static function convert_exception_to_verbose_string($exception)
    {
        if ($exception instanceof Exception) {
            $message = "Exception ({$exception->get_name()})";
        } elseif ($exception instanceof ErrorException) {
            $message = (string) $exception->get_name();
        } else {
            $message = 'Exception';
        }
        return $message . (" '" . get_class($exception) . "' with message '{$exception->get_message()}' \n\nin " . $exception->get_file() . ':' . $exception->get_line() . "\n\n" . "Stack trace:\n" . $exception->get_trace_as_string());
    }
}