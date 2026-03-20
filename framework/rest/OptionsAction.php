<?php

declare (strict_types=1);
/**
 * @link https://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license https://www.yiiframework.com/license/
 */
namespace yii\rest;

use Yii;
use yii\base\Action as BaseAction;
/**
 * OptionsAction responds to the OPTIONS request by sending back an `Allow` header.
 *
 * For more details and usage information on OptionsAction, see the [guide article on rest controllers](guide:rest-controllers).
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 *
 * @template T of Controller = Controller
 * @extends BaseAction<T>
 */
class Options_Action extends Base_Action
{
    /**
     * @var array the HTTP verbs that are supported by the collection URL
     */
    public $collection_options = ['GET', 'POST', 'HEAD', 'OPTIONS'];
    /**
     * @var array the HTTP verbs that are supported by the resource URL
     */
    public $resource_options = ['GET', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'];
    /**
     * Responds to the OPTIONS request.
     * @param string|null $id
     */
    public function run($id = null): void
    {
        if (Yii::$app->get_request()->get_method() !== 'OPTIONS') {
            Yii::$app->get_response()->set_status_code(405);
        }
        $options = $id === null ? $this->collection_options : $this->resource_options;
        $headers = Yii::$app->get_response()->get_headers();
        $headers->set('Allow', implode(', ', $options));
        $headers->set('Access-Control-Allow-Methods', implode(', ', $options));
    }
}