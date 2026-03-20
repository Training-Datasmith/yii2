<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\web;

use Yii;
use yii\base\Invalid_Route_Exception;
use yii\helpers\Url;
/**
 * Application is the base class for all web application classes.
 *
 * For more details and usage information on Application, see the [guide article on applications](guide:structure-applications).
 *
 * @template TUserIdentity of IdentityInterface = IdentityInterface
 *
 * @property-read ErrorHandler $errorHandler The error handler application component.
 * @property string $homeUrl The homepage URL.
 * @property-read Request $request The request component.
 * @property-read Response $response The response component.
 * @property-read Session $session The session component.
 * @property-read User<TUserIdentity> $user The user component.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Application extends \yii\base\Application
{
    /**
     * @var string the default route of this application. Defaults to 'site'.
     */
    public $default_route = 'site';
    /**
     * @var array|null the configuration specifying a controller action which should handle
     * all user requests. This is mainly used when the application is in maintenance mode
     * and needs to handle all incoming requests via a single action.
     * The configuration is an array whose first element specifies the route of the action.
     * The rest of the array elements (key-value pairs) specify the parameters to be bound
     * to the action. For example,
     *
     * ```
     * [
     *     'offline/notice',
     *     'param1' => 'value1',
     *     'param2' => 'value2',
     * ]
     * ```
     *
     * Defaults to null, meaning catch-all is not used.
     */
    public $catch_all;
    /**
     * @var Controller|null the currently active controller instance
     */
    public $controller;
    /**
     * {@inheritdoc}
     */
    protected function bootstrap()
    {
        $request = $this->get_request();
        Yii::set_alias('@webroot', dirname($request->get_script_file()));
        Yii::set_alias('@web', $request->get_base_url());
        parent::bootstrap();
    }
    /**
     * Handles the specified request.
     * @param Request $request the request to be handled
     * @return Response the resulting response
     * @throws NotFoundHttpException if the requested route is invalid
     */
    public function handle_request($request)
    {
        if (empty($this->catch_all)) {
            try {
                [$route, $params] = $request->resolve();
            } catch (Url_Normalizer_Redirect_Exception $e) {
                $url = $e->url;
                if (is_array($url)) {
                    if (isset($url[0])) {
                        // ensure the route is absolute
                        $url[0] = '/' . ltrim($url[0], '/');
                    }
                    $url += $request->get_query_params();
                }
                return $this->get_response()->redirect(Url::to($url, $e->scheme), $e->status_code);
            }
        } else {
            $route = $this->catch_all[0];
            $params = $this->catch_all;
            unset($params[0]);
        }
        try {
            Yii::debug("Route requested: '{$route}'", __METHOD__);
            $this->requested_route = $route;
            $result = $this->run_action($route, $params);
            if ($result instanceof Response) {
                return $result;
            }
            $response = $this->get_response();
            if ($result !== null) {
                $response->data = $result;
            }
            return $response;
        } catch (Invalid_Route_Exception $e) {
            throw new Not_Found_Http_Exception(Yii::t('yii', 'Page not found.'), $e->get_code(), $e);
        }
    }
    private $_home_url;
    /**
     * @return string the homepage URL
     */
    public function get_home_url()
    {
        if ($this->_home_url === null) {
            if ($this->get_url_manager()->show_script_name) {
                return $this->get_request()->get_script_url();
            }
            return $this->get_request()->get_base_url() . '/';
        }
        return $this->_home_url;
    }
    /**
     * @param string $value the homepage URL
     */
    public function set_home_url($value): void
    {
        $this->_home_url = $value;
    }
    /**
     * Returns the error handler component.
     * @return ErrorHandler the error handler application component.
     */
    public function get_error_handler()
    {
        return $this->get('errorHandler');
    }
    /**
     * Returns the request component.
     * @return Request the request component.
     */
    public function get_request()
    {
        return $this->get('request');
    }
    /**
     * Returns the response component.
     * @return Response the response component.
     */
    public function get_response()
    {
        return $this->get('response');
    }
    /**
     * Returns the session component.
     * @return Session the session component.
     */
    public function get_session()
    {
        return $this->get('session');
    }
    /**
     * Returns the user component.
     * @return User<TUserIdentity> the user component.
     */
    public function get_user()
    {
        return $this->get('user');
    }
    /**
     * {@inheritdoc}
     */
    public function core_components(): array
    {
        return array_merge(parent::core_components(), ['request' => ['class' => 'yii\web\Request'], 'response' => ['class' => 'yii\web\Response'], 'session' => ['class' => 'yii\web\Session'], 'user' => ['class' => 'yii\web\User'], 'errorHandler' => ['class' => 'yii\web\ErrorHandler']]);
    }
}