<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters;

use Closure;
use Yii;
use yii\base\Action;
use yii\base\Action_Filter;
use yii\base\Component;
use yii\base\Controller;
use yii\base\Module;
use yii\web\Identity_Interface;
use yii\web\Request;
use yii\web\Response;
use yii\web\Too_Many_Requests_Http_Exception;
/**
 * RateLimiter implements a rate limiting algorithm based on the [leaky bucket algorithm](https://en.wikipedia.org/wiki/Leaky_bucket).
 *
 * You may use RateLimiter by attaching it as a behavior to a controller or module, like the following,
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         'rateLimiter' => [
 *             'class' => \yii\filters\RateLimiter::class,
 *         ],
 *     ];
 * }
 * ```
 *
 * When the user has exceeded his rate limit, RateLimiter will throw a [[TooManyRequestsHttpException]] exception.
 *
 * Note that RateLimiter requires [[user]] to implement the [[RateLimitInterface]]. RateLimiter will
 * do nothing if [[user]] is not set or does not implement [[RateLimitInterface]].
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Component = Component
 * @extends ActionFilter<T>
 */
class Rate_Limiter extends Action_Filter
{
    /**
     * @var bool whether to include rate limit headers in the response
     */
    public $enable_rate_limit_headers = true;
    /**
     * @var string the message to be displayed when rate limit exceeds
     */
    public $error_message = 'Rate limit exceeded.';
    /**
     * @var RateLimitInterface|IdentityInterface|Closure|null the user object that implements the RateLimitInterface. If not set, it will take the value of `Yii::$app->user->getIdentity(false)`.
     * {@since 2.0.38} It's possible to provide a closure function in order to assign the user identity on runtime. Using a closure to assign the user identity is recommend
     * when you are **not** using the standard `Yii::$app->user` component. See the example below:
     * ```
     * 'user' => function() {
     *     return Yii::$app->apiUser->identity;
     * }
     * ```
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
     * {@inheritdoc}
     */
    public function init(): void
    {
        if ($this->request === null) {
            $this->request = Yii::$app->get_request();
        }
        if ($this->response === null) {
            $this->response = Yii::$app->get_response();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        if ($this->user === null && Yii::$app->get_user()) {
            $this->user = Yii::$app->get_user()->get_identity(false);
        }
        if ($this->user instanceof Closure) {
            $this->user = call_user_func($this->user, $action);
        }
        if ($this->user instanceof Rate_Limit_Interface) {
            Yii::debug('Check rate limit', __METHOD__);
            $this->check_rate_limit($this->user, $this->request, $this->response, $action);
        } elseif ($this->user) {
            Yii::info('Rate limit skipped: "user" does not implement RateLimitInterface.', __METHOD__);
        } else {
            Yii::info('Rate limit skipped: user not logged in.', __METHOD__);
        }
        return true;
    }
    /**
     * Checks whether the rate limit exceeds.
     * @param RateLimitInterface $user the current user
     * @param Request $request
     * @param Response $response
     * @param Action $action the action to be executed
     * @throws TooManyRequestsHttpException if rate limit exceeds
     */
    public function check_rate_limit($user, $request, $response, $action): void
    {
        [$limit, $window] = $user->get_rate_limit($request, $action);
        [$allowance, $timestamp] = $user->load_allowance($request, $action);
        $current = time();
        $allowance += (int) (($current - $timestamp) * $limit / $window);
        if ($allowance > $limit) {
            $allowance = $limit;
        }
        if ($allowance < 1) {
            $user->save_allowance($request, $action, 0, $current);
            $this->add_rate_limit_headers($response, $limit, 0, $window);
            throw new Too_Many_Requests_Http_Exception($this->error_message);
        }
        $user->save_allowance($request, $action, $allowance - 1, $current);
        $this->add_rate_limit_headers($response, $limit, $allowance - 1, (int) (($limit - $allowance + 1) * $window / $limit));
    }
    /**
     * Adds the rate limit headers to the response.
     * @param Response $response
     * @param int $limit the maximum number of allowed requests during a period
     * @param int $remaining the remaining number of allowed requests within the current period
     * @param int $reset the number of seconds to wait before having maximum number of allowed requests again
     */
    public function add_rate_limit_headers($response, $limit, $remaining, $reset): void
    {
        if ($this->enable_rate_limit_headers) {
            $response->get_headers()->set('X-Rate-Limit-Limit', $limit)->set('X-Rate-Limit-Remaining', $remaining)->set('X-Rate-Limit-Reset', $reset);
        }
    }
}