<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\filters;

use Yii;
use yii\base\Action_Filter;
use yii\base\Component;
use yii\web\Bad_Request_Http_Exception;
use yii\web\Request;
/**
 * AjaxFilter allow to limit access only for ajax requests.
 *
 * ```
 * public function behaviors()
 * {
 *     return [
 *         [
 *             'class' => 'yii\filters\AjaxFilter',
 *             'only' => ['index']
 *         ],
 *     ];
 * }
 * ```
 *
 * @author Dmitry Dorogin <dmirogin@ya.ru>
 * @since 2.0.13
 *
 * @template T of Component = Component
 * @extends ActionFilter<T>
 */
class Ajax_Filter extends Action_Filter
{
    /**
     * @var string the message to be displayed when request isn't ajax
     */
    public $error_message = 'Request must be XMLHttpRequest.';
    /**
     * @var Request|null the current request. If not set, the `request` application component will be used.
     */
    public $request;
    /**
     * {@inheritdoc}
     */
    public function init(): void
    {
        if ($this->request === null) {
            $this->request = Yii::$app->get_request();
        }
    }
    /**
     * {@inheritdoc}
     */
    public function before_action($action): bool
    {
        if ($this->request->get_is_ajax()) {
            return true;
        }
        throw new Bad_Request_Http_Exception($this->error_message);
    }
}