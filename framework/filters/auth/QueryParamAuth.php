<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters\auth;

use yii\base\Component;
/**
 * QueryParamAuth is an action filter that supports the authentication based on the access token passed through a query parameter.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Component = Component
 * @extends AuthMethod<T>
 */
class Query_Param_Auth extends Auth_Method
{
    /**
     * @var string the parameter name for passing the access token
     */
    public $token_param = 'access-token';
    /**
     * {@inheritdoc}
     */
    public function authenticate($user, $request, $response)
    {
        $access_token = $request->get($this->token_param);
        if (is_string($access_token)) {
            $identity = $user->login_by_access_token($access_token, get_class($this));
            if ($identity !== null) {
                return $identity;
            }
        }
        if ($access_token !== null) {
            $this->handle_failure($response);
        }
        return null;
    }
}