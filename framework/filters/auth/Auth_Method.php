<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters\auth;

use Yii;
use yii\base\Action;
use yii\base\Action_Filter;
use yii\base\Component;
use yii\helpers\String_Helper;
use yii\web\Request;
use yii\web\Response;
use yii\web\Unauthorized_Http_Exception;
use yii\web\User;
/**
 * AuthMethod is a base class implementing the [[AuthInterface]] interface.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Component = Component
 * @extends ActionFilter<T>
 */
abstract class Auth_Method extends Action_Filter implements Auth_Interface
{
    /**
     * @var User|null the user object representing the user authentication status. If not set, the `user` application component will be used.
     */
    public $user;
    /**
     * @var Request|null the current request. If not set, the `request` application component will be used.
     */
    public $request;
    /**
     * @var Response|null the response to be sent. If not set, the `response` application component will be used.
     */
    public $response;
    /**
     * @var array list of action IDs that this filter will be applied to, but auth failure will not lead to error.
     * It may be used for actions, that are allowed for public, but return some additional data for authenticated users.
     * Defaults to empty, meaning authentication is not optional for any action.
     * Since version 2.0.10 action IDs can be specified as wildcards, e.g. `site/*`.
     * @see isOptional()
     * @since 2.0.7
     */
    public $optional = [];
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        $response = $this->response ?: Yii::$app->get_response();
        try {
            $identity = $this->authenticate($this->user ?: Yii::$app->get_user(), $this->request ?: Yii::$app->get_request(), $response);
        } catch (Unauthorized_Http_Exception $e) {
            if ($this->is_optional($action)) {
                return true;
            }
            throw $e;
        }
        if ($identity !== null || $this->is_optional($action)) {
            return true;
        }
        $this->challenge($response);
        $this->handle_failure($response);
        return false;
    }
    /**
     * {@inheritdoc}
     */
    public function challenge($response)
    {
    }
    /**
     * {@inheritdoc}
     */
    public function handle_failure($response)
    {
        throw new Unauthorized_Http_Exception('Your request was made with invalid credentials.');
    }
    /**
     * Checks, whether authentication is optional for the given action.
     *
     * @param Action $action action to be checked.
     * @return bool whether authentication is optional or not.
     * @see optional
     * @since 2.0.7
     */
    protected function is_optional($action)
    {
        $id = $this->get_action_id($action);
        foreach ($this->optional as $pattern) {
            if (String_Helper::match_wildcard($pattern, $id)) {
                return true;
            }
        }
        return false;
    }
}