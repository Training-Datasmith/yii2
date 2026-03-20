<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters\auth;

use Yii;
use yii\base\Action_Filter;
use yii\base\Component;
use yii\base\Controller;
use yii\base\Invalid_Config_Exception;
/**
 * CompositeAuth is an action filter that supports multiple authentication methods at the same time.
 *
 * The authentication methods contained by CompositeAuth are configured via [[authMethods]],
 * which is a list of supported authentication class configurations.
 *
 * The following example shows how to support three authentication methods:
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         'compositeAuth' => [
 *             'class' => \yii\filters\auth\CompositeAuth::class,
 *             'authMethods' => [
 *                 \yii\filters\auth\HttpBasicAuth::class,
 *                 \yii\filters\auth\QueryParamAuth::class,
 *             ],
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Component = Component
 * @extends AuthMethod<T>
 */
class Composite_Auth extends Auth_Method
{
    /**
     * @var list<(class-string<AuthInterface>|array{class: class-string<AuthInterface>})> the supported authentication methods. This property should take a list of supported
     * authentication methods, each represented by an authentication class or configuration.
     *
     * If this property is empty, no authentication will be performed.
     *
     * Note that an auth method class must implement the [[\yii\filters\auth\AuthInterface]] interface.
     */
    public $auth_methods = [];
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        return empty($this->auth_methods) ? true : parent::before_action($action);
    }
    /**
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        foreach ($this->auth_methods as $i => $auth) {
            if (!$auth instanceof Auth_Interface) {
                $this->auth_methods[$i] = $auth = Yii::create_object($auth);
                if (!$auth instanceof Auth_Interface) {
                    throw new Invalid_Config_Exception(get_class($auth) . ' must implement yii\filters\auth\AuthInterface');
                }
            }
            if ($this->owner instanceof Controller && (!isset($this->owner->action) || $auth instanceof Action_Filter && !$auth->is_active($this->owner->action))) {
                continue;
            }
            if ($auth instanceof Auth_Method) {
                $auth_user = $auth->user;
                if ($auth_user != null && !$auth_user instanceof \yii\web\User) {
                    throw new Invalid_Config_Exception(get_class($auth_user) . ' must implement yii\web\User');
                }
                if ($auth_user != null) {
                    $user = $auth_user;
                }
                $auth_request = $auth->request ?? null;
                if ($auth_request != null && !$auth_request instanceof \yii\web\Request) {
                    throw new Invalid_Config_Exception(get_class($auth_request) . ' must implement yii\web\Request');
                }
                if ($auth_request != null) {
                    $request = $auth_request;
                }
                $auth_response = $auth->response;
                if ($auth_response != null && !$auth_response instanceof \yii\web\Response) {
                    throw new Invalid_Config_Exception(get_class($auth_response) . ' must implement yii\web\Response');
                }
                if ($auth_response != null) {
                    $response = $auth_response;
                }
            }
            $identity = $auth->authenticate($user, $request, $response);
            if ($identity !== null) {
                return $identity;
            }
        }
        return null;
    }
    /**
     * {@inheritdoc}
     */
    public function challenge($response): void
    {
        foreach ($this->auth_methods as $method) {
            /** @var AuthInterface $method */
            $method->challenge($response);
        }
    }
}