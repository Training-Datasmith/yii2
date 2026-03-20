<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters;

use Yii;
use yii\base\Action;
use yii\base\Action_Filter;
use yii\base\Component;
use yii\base\Controller;
use yii\helpers\String_Helper;
use yii\web\Not_Found_Http_Exception;
/**
 * HostControl provides simple control over requested host name.
 *
 * This filter provides protection against ['host header' attacks](https://www.acunetix.com/vulnerabilities/web/host-header-attack),
 * allowing action execution only for specified host names.
 *
 * Application configuration example:
 *
 * ```
 * return [
 *     'as hostControl' => [
 *         'class' => 'yii\filters\HostControl',
 *         'allowedHosts' => [
 *             'example.com',
 *             '*.example.com',
 *         ],
 *     ],
 *     // ...
 * ];
 * ```
 *
 * Controller configuration example:
 *
 * ```
 * use yii\web\Controller;
 * use yii\filters\HostControl;
 *
 * class SiteController extends Controller
 * {
 *     public function behaviors()
 *     {
 *         return [
 *             'hostControl' => [
 *                 'class' => HostControl::class,
 *                 'allowedHosts' => [
 *                     'example.com',
 *                     '*.example.com',
 *                 ],
 *             ],
 *         ];
 *     }
 *
 *     // ...
 * }
 * ```
 *
 * > Note: the best way to restrict allowed host names is usage of the web server 'virtual hosts' configuration.
 * This filter should be used only if this configuration is not available or compromised.
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 2.0.11
 *
 * @template T of Component = Component
 * @extends ActionFilter<T>
 */
class Host_Control extends Action_Filter
{
    /**
     * @var array|\Closure|null list of host names, which are allowed.
     * Each host can be specified as a wildcard pattern. For example:
     *
     * ```
     * [
     *     'example.com',
     *     '*.example.com',
     * ]
     * ```
     *
     * This field can be specified as a PHP callback of following signature:
     *
     * ```
     * function (\yii\base\Action $action) {
     *     //return array of strings
     * }
     * ```
     *
     * where `$action` is the current [[\yii\base\Action|action]] object.
     *
     * If this field is not set - no host name check will be performed.
     */
    public $allowed_hosts;
    /**
     * @var callable|null a callback that will be called if the current host does not match [[allowedHosts]].
     * If not set, [[denyAccess()]] will be called.
     *
     * The signature of the callback should be as follows:
     *
     * ```
     * function (\yii\base\Action $action)
     * ```
     *
     * where `$action` is the current [[\yii\base\Action|action]] object.
     *
     * > Note: while implementing your own host deny processing, make sure you avoid usage of the current requested
     * host name, creation of absolute URL links, caching page parts and so on.
     */
    public $deny_callback;
    /**
     * @var string|null fallback host info (e.g. `https://www.yiiframework.com`) used when [[\yii\web\Request::$hostInfo|Request::$hostInfo]] is invalid.
     * This value will replace [[\yii\web\Request::$hostInfo|Request::$hostInfo]] before [[$denyCallback]] is called to make sure that
     * an invalid host will not be used for further processing. You can set it to `null` to leave [[\yii\web\Request::$hostInfo|Request::$hostInfo]] untouched.
     * Default value is empty string (this will result creating relative URLs instead of absolute).
     * @see \yii\web\Request::getHostInfo()
     */
    public $fallback_host_info = '';
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        $allowed_hosts = $this->allowed_hosts;
        if ($allowed_hosts instanceof \Closure) {
            $allowed_hosts = call_user_func($allowed_hosts, $action);
        }
        if ($allowed_hosts === null) {
            return true;
        }
        if (!is_array($allowed_hosts) && !$allowed_hosts instanceof \Traversable) {
            $allowed_hosts = (array) $allowed_hosts;
        }
        $current_host = Yii::$app->get_request()->get_host_name();
        foreach ($allowed_hosts as $allowed_host) {
            if (String_Helper::match_wildcard($allowed_host, $current_host)) {
                return true;
            }
        }
        // replace invalid host info to prevent using it in further processing
        if ($this->fallback_host_info !== null) {
            Yii::$app->get_request()->set_host_info($this->fallback_host_info);
        }
        if ($this->deny_callback !== null) {
            call_user_func($this->deny_callback, $action);
        } else {
            $this->deny_access($action);
        }
        return false;
    }
    /**
     * Denies the access.
     * The default implementation will display 404 page right away, terminating the program execution.
     * You may override this method, creating your own deny access handler. While doing so, make sure you
     * avoid usage of the current requested host name, creation of absolute URL links, caching page parts and so on.
     * @param Action $action the action to be executed.
     * @throws NotFoundHttpException
     */
    protected function deny_access($action)
    {
        $exception = new Not_Found_Http_Exception(Yii::t('yii', 'Page not found.'));
        // use regular error handling if $this->fallbackHostInfo was set
        if (!empty(Yii::$app->get_request()->host_name)) {
            throw $exception;
        }
        $response = Yii::$app->get_response();
        $error_handler = Yii::$app->get_error_handler();
        $response->set_status_code($exception->status_code, $exception->get_message());
        $response->data = $error_handler->render_file($error_handler->error_view, ['exception' => $exception]);
        $response->send();
        Yii::$app->end();
    }
}