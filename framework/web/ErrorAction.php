<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Action;
use yii\base\Exception;
use yii\base\User_Exception;
/**
 * ErrorAction displays application errors using a specified view.
 *
 * To use ErrorAction, you need to do the following steps:
 *
 * First, declare an action of ErrorAction type in the `actions()` method of your `SiteController`
 * class (or whatever controller you prefer), like the following:
 *
 * ```
 * public function actions()
 * {
 *     return [
 *         'error' => ['class' => 'yii\web\ErrorAction'],
 *     ];
 * }
 * ```
 *
 * Then, create a view file for this action. If the route of your error action is `site/error`, then
 * the view file should be `views/site/error.php`. In this view file, the following variables are available:
 *
 * - `$name`: the error name
 * - `$message`: the error message
 * - `$exception`: the exception being handled
 *
 * Finally, configure the "errorHandler" application component as follows,
 *
 * ```
 * 'errorHandler' => [
 *     'errorAction' => 'site/error',
 * ]
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @author Dmitry Naumenko <d.naumenko.a@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends Action<T>
 */
class Error_Action extends Action
{
    /**
     * @var string|null the view file to be rendered. If not set, it will take the value of [[id]].
     * That means, if you name the action as "error" in "SiteController", then the view name
     * would be "error", and the corresponding view file would be "views/site/error.php".
     */
    public $view;
    /**
     * @var string the name of the error when the exception name cannot be determined.
     * Defaults to "Error".
     */
    public $default_name;
    /**
     * @var string the message to be displayed when the exception message contains sensitive information.
     * Defaults to "An internal server error occurred.".
     */
    public $default_message;
    /**
     * @var string|null|false the name of the layout to be applied to this error action view.
     * If not set, the layout configured in the controller will be used.
     * @see \yii\base\Controller::$layout
     * @since 2.0.14
     */
    public $layout;
    /**
     * @var \Throwable the exception object, normally is filled on [[init()]] method call.
     * @see findException() to know default way of obtaining exception.
     * @since 2.0.11
     */
    protected $exception;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        $this->exception = $this->find_exception();
        if ($this->default_message === null) {
            $this->default_message = Yii::t('yii', 'An internal server error occurred.');
        }
        if ($this->default_name === null) {
            $this->default_name = Yii::t('yii', 'Error');
        }
    }
    /**
     * Runs the action.
     *
     * @return string result content
     */
    public function run()
    {
        if ($this->layout !== null) {
            $this->controller->layout = $this->layout;
        }
        Yii::$app->get_response()->set_status_code_by_exception($this->exception);
        if (Yii::$app->get_request()->get_is_ajax()) {
            return $this->render_ajax_response();
        }
        return $this->render_html_response();
    }
    /**
     * Builds string that represents the exception.
     * Normally used to generate a response to AJAX request.
     * @since 2.0.11
     */
    protected function render_ajax_response(): string
    {
        return $this->get_exception_name() . ': ' . $this->get_exception_message();
    }
    /**
     * Renders a view that represents the exception.
     * @return string
     * @since 2.0.11
     */
    protected function render_html_response()
    {
        return $this->controller->render($this->view ?: $this->id, $this->get_view_render_params());
    }
    /**
     * Builds array of parameters that will be passed to the view.
     * @since 2.0.11
     */
    protected function get_view_render_params(): array
    {
        return ['name' => $this->get_exception_name(), 'message' => $this->get_exception_message(), 'exception' => $this->exception];
    }
    /**
     * Gets exception from the [[yii\web\ErrorHandler|ErrorHandler]] component.
     * In case there is no exception in the component, treat as the action has been invoked
     * not from error handler, but by direct route, so '404 Not Found' error will be displayed.
     * @return \Throwable
     * @since 2.0.11
     */
    protected function find_exception()
    {
        if (($exception = Yii::$app->get_error_handler()->exception) === null) {
            return new Not_Found_Http_Exception(Yii::t('yii', 'Page not found.'));
        }
        return $exception;
    }
    /**
     * Gets the code from the [[exception]].
     * @return mixed
     * @since 2.0.11
     */
    protected function get_exception_code()
    {
        if ($this->exception instanceof Http_Exception) {
            return $this->exception->status_code;
        }
        return $this->exception->get_code();
    }
    /**
     * Returns the exception name, followed by the code (if present).
     *
     * @return string
     * @since 2.0.11
     */
    protected function get_exception_name()
    {
        if ($this->exception instanceof Exception) {
            $name = $this->exception->get_name();
        } else {
            $name = $this->default_name;
        }
        if ($code = $this->get_exception_code()) {
            $name .= " (#{$code})";
        }
        return $name;
    }
    /**
     * Returns the [[exception]] message for [[yii\base\UserException]] only.
     * For other cases [[defaultMessage]] will be returned.
     * @return string
     * @since 2.0.11
     */
    protected function get_exception_message()
    {
        if ($this->exception instanceof User_Exception) {
            return $this->exception->get_message();
        }
        return $this->default_message;
    }
}